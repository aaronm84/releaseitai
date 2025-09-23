<?php

namespace App\Jobs;

use App\Models\Output;
use App\Models\Feedback;
use App\Services\FeedbackService;
use App\Services\RetrievalService;
use App\Exceptions\AiServiceException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Exception;

class ProcessFeedbackLearningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 240; // 4 minutes
    public $tries = 3;
    public $maxExceptions = 1;
    public $backoff = [60, 120, 240];

    protected Output $output;
    protected array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(Output $output, array $options = [])
    {
        $this->output = $output;
        $this->options = $options;

        // Always use low priority for feedback learning
        $this->onQueue('low-priority');
    }

    /**
     * Execute the job.
     */
    public function handle(
        FeedbackService $feedbackService,
        RetrievalService $retrievalService
    ): void {
        $lockKey = "process_feedback_learning_{$this->output->id}";

        // Prevent duplicate processing
        if (!Cache::lock($lockKey, 480)->get()) {
            Log::warning("Feedback learning processing already in progress", [
                'output_id' => $this->output->id
            ]);
            return;
        }

        try {
            Log::info("Starting feedback learning processing", [
                'output_id' => $this->output->id,
                'input_id' => $this->output->input_id,
                'attempt' => $this->attempts()
            ]);

            // Get all feedback for this output
            $feedback = $this->collectFeedback();

            if ($feedback->isEmpty()) {
                Log::info("No feedback available for learning", [
                    'output_id' => $this->output->id
                ]);
                return;
            }

            // Process feedback patterns
            $patterns = $this->processFeedbackPatterns($feedbackService, $feedback);

            // Update retrieval knowledge base if we have positive feedback
            $this->updateRetrievalKnowledge($retrievalService, $feedback, $patterns);

            // Update output metadata with learning results
            $this->updateOutputMetadata($patterns);

            Log::info("Feedback learning processing completed successfully", [
                'output_id' => $this->output->id,
                'feedback_count' => $feedback->count(),
                'patterns_found' => count($patterns),
                'positive_feedback' => $feedback->where('action', 'accept')->count()
            ]);

        } catch (AiServiceException $e) {
            $this->handleAiServiceException($e);
        } catch (Exception $e) {
            $this->handleGeneralException($e);
        } finally {
            Cache::lock($lockKey)->release();
        }
    }

    /**
     * Collect feedback for this output
     */
    protected function collectFeedback(): Collection
    {
        return Feedback::where('output_id', $this->output->id)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Process feedback patterns to extract learning insights
     */
    protected function processFeedbackPatterns(
        FeedbackService $feedbackService,
        Collection $feedback
    ): array {
        $patterns = [];

        // Analyze feedback trends
        $patterns['feedback_summary'] = [
            'total_feedback' => $feedback->count(),
            'positive_feedback' => $feedback->where('action', 'accept')->count(),
            'negative_feedback' => $feedback->whereIn('action', ['reject', 'edit'])->count(),
            'average_confidence' => $feedback->avg('confidence'),
            'feedback_types' => $feedback->groupBy('type')->map->count()
        ];

        // Extract common feedback themes
        $patterns['themes'] = $this->extractFeedbackThemes($feedback);

        // Analyze user corrections and edits
        $patterns['corrections'] = $this->analyzeUserCorrections($feedback);

        // Identify quality indicators
        $patterns['quality_indicators'] = $this->identifyQualityIndicators($feedback);

        return $patterns;
    }

    /**
     * Extract common themes from feedback
     */
    protected function extractFeedbackThemes(Collection $feedback): array
    {
        $themes = [];

        foreach ($feedback as $fb) {
            if ($fb->metadata && isset($fb->metadata['edit_reason'])) {
                $reason = $fb->metadata['edit_reason'];
                $themes[$reason] = ($themes[$reason] ?? 0) + 1;
            }

            if ($fb->metadata && isset($fb->metadata['feedback_category'])) {
                $category = $fb->metadata['feedback_category'];
                $themes[$category] = ($themes[$category] ?? 0) + 1;
            }
        }

        // Sort by frequency
        arsort($themes);

        return array_slice($themes, 0, 10); // Top 10 themes
    }

    /**
     * Analyze user corrections for learning opportunities
     */
    protected function analyzeUserCorrections(Collection $feedback): array
    {
        $corrections = [];

        foreach ($feedback as $fb) {
            if ($fb->action === 'edit' && $fb->metadata && isset($fb->metadata['corrected_content'])) {
                $corrections[] = [
                    'original_content' => $this->output->content,
                    'corrected_content' => $fb->metadata['corrected_content'],
                    'edit_reason' => $fb->metadata['edit_reason'] ?? null,
                    'confidence' => $fb->confidence,
                    'user_id' => $fb->user_id,
                    'timestamp' => $fb->created_at
                ];
            }
        }

        return $corrections;
    }

    /**
     * Identify quality indicators from feedback
     */
    protected function identifyQualityIndicators(Collection $feedback): array
    {
        $indicators = [];

        // High confidence positive feedback indicates good quality
        $highConfidencePositive = $feedback
            ->where('action', 'accept')
            ->where('confidence', '>=', 0.8)
            ->count();

        // Low confidence or negative feedback indicates areas for improvement
        $improvementAreas = $feedback
            ->where(function ($fb) {
                return $fb->confidence < 0.6 || in_array($fb->action, ['reject', 'edit']);
            })
            ->pluck('metadata.feedback_category')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $indicators['high_quality_signals'] = $highConfidencePositive;
        $indicators['improvement_areas'] = $improvementAreas;
        $indicators['overall_quality_score'] = $this->calculateQualityScore($feedback);

        return $indicators;
    }

    /**
     * Calculate overall quality score based on feedback
     */
    protected function calculateQualityScore(Collection $feedback): float
    {
        if ($feedback->isEmpty()) {
            return 0.5; // Neutral score for no feedback
        }

        $totalWeight = 0;
        $weightedScore = 0;

        foreach ($feedback as $fb) {
            $weight = $fb->confidence;
            $totalWeight += $weight;

            // Score based on action
            $score = match ($fb->action) {
                'accept' => 1.0,
                'copy' => 0.8,
                'edit' => 0.6,
                'reject' => 0.2,
                default => 0.5
            };

            $weightedScore += $score * $weight;
        }

        return $totalWeight > 0 ? $weightedScore / $totalWeight : 0.5;
    }

    /**
     * Update retrieval knowledge base with positive examples
     */
    protected function updateRetrievalKnowledge(
        RetrievalService $retrievalService,
        Collection $feedback,
        array $patterns
    ): void {
        // Only update knowledge base with high-quality positive feedback
        $positiveHighConfidence = $feedback
            ->where('action', 'accept')
            ->where('confidence', '>=', 0.8);

        if ($positiveHighConfidence->isNotEmpty()) {
            // Mark this input-output pair as a good example for RAG
            $this->output->update([
                'feedback_integrated' => true,
                'quality_score' => $patterns['quality_indicators']['overall_quality_score']
            ]);

            // Cache successful patterns for future use
            $cacheKey = "successful_pattern_{$this->output->type}_{$this->output->input->type}";
            Cache::put($cacheKey, [
                'input_content' => $this->output->input->content,
                'output_content' => $this->output->content,
                'feedback_summary' => $patterns['feedback_summary'],
                'quality_score' => $patterns['quality_indicators']['overall_quality_score']
            ], now()->addDays(30));
        }
    }

    /**
     * Update output metadata with learning results
     */
    protected function updateOutputMetadata(array $patterns): void
    {
        $metadata = $this->output->metadata ?? [];
        $metadata['feedback_learning'] = array_merge($patterns, [
            'processed_at' => now()->toISOString(),
            'job_id' => $this->job->getJobId(),
            'learning_enabled' => true
        ]);

        $this->output->update(['metadata' => $metadata]);
    }

    /**
     * Handle AI service specific exceptions
     */
    protected function handleAiServiceException(AiServiceException $e): void
    {
        Log::error("AI service error in feedback learning processing", [
            'output_id' => $this->output->id,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'attempt' => $this->attempts()
        ]);

        throw $e;
    }

    /**
     * Handle general exceptions
     */
    protected function handleGeneralException(Exception $e): void
    {
        Log::error("General error in feedback learning processing", [
            'output_id' => $this->output->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'attempt' => $this->attempts()
        ]);

        throw $e;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return $this->backoff;
    }

    /**
     * The job failed to process.
     */
    public function failed(Exception $exception): void
    {
        Log::error("Feedback learning job failed permanently", [
            'output_id' => $this->output->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Update output metadata with failure information
        $metadata = $this->output->metadata ?? [];
        $metadata['feedback_learning_failed'] = [
            'error' => $exception->getMessage(),
            'failed_at' => now()->toISOString(),
            'total_attempts' => $this->attempts()
        ];

        $this->output->update(['metadata' => $metadata]);
    }
}