<?php

namespace App\Jobs;

use App\Models\Output;
use App\Models\Input;
use App\Services\AiService;
use App\Exceptions\AiServiceException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class GenerateAiContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes for AI content generation
    public $tries = 3;
    public $maxExceptions = 1;
    public $backoff = [60, 180, 300]; // Longer backoff for AI operations

    protected Input $input;
    protected array $options;
    protected ?string $outputType;

    /**
     * Create a new job instance.
     */
    public function __construct(Input $input, ?string $outputType = null, array $options = [])
    {
        $this->input = $input;
        $this->outputType = $outputType;
        $this->options = $options;

        // Set queue priority based on content type and user
        $this->onQueue($this->determineQueuePriority());
    }

    /**
     * Execute the job.
     */
    public function handle(AiService $aiService): void
    {
        $lockKey = "generate_ai_content_{$this->input->id}";

        // Prevent duplicate processing
        if (!Cache::lock($lockKey, 900)->get()) {
            Log::warning("AI content generation already in progress", [
                'input_id' => $this->input->id
            ]);
            return;
        }

        try {
            Log::info("Starting AI content generation", [
                'input_id' => $this->input->id,
                'output_type' => $this->outputType,
                'attempt' => $this->attempts()
            ]);

            // Track AI operation costs
            $startTime = microtime(true);

            // Generate content using AI service
            $generatedContent = $aiService->generateContent(
                $this->input->content,
                $this->outputType,
                $this->options
            );

            $processingTime = microtime(true) - $startTime;

            // Create output record
            $output = Output::create([
                'input_id' => $this->input->id,
                'content' => $generatedContent['content'],
                'type' => $this->outputType ?? 'ai_generated',
                'ai_model' => $generatedContent['model'] ?? 'unknown',
                'quality_score' => $generatedContent['quality_score'] ?? null,
                'metadata' => [
                    'generation_options' => $this->options,
                    'processing_time_seconds' => $processingTime,
                    'tokens_used' => $generatedContent['tokens_used'] ?? null,
                    'cost_usd' => $generatedContent['cost_usd'] ?? null,
                    'generated_by_job' => true,
                    'job_id' => $this->job->getJobId()
                ]
            ]);

            // Update input status
            $this->input->update(['status' => 'processed']);

            // Dispatch follow-up jobs
            $this->dispatchFollowupJobs($output, $generatedContent);

            // Log successful completion
            Log::info("AI content generation completed successfully", [
                'input_id' => $this->input->id,
                'output_id' => $output->id,
                'processing_time' => $processingTime,
                'tokens_used' => $generatedContent['tokens_used'] ?? 0,
                'cost_usd' => $generatedContent['cost_usd'] ?? 0
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
     * Handle AI service specific exceptions
     */
    protected function handleAiServiceException(AiServiceException $e): void
    {
        Log::error("AI service error in content generation", [
            'input_id' => $this->input->id,
            'output_type' => $this->outputType,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'attempt' => $this->attempts()
        ]);

        // Create failed output on final attempt
        if ($this->attempts() >= $this->tries) {
            Output::create([
                'input_id' => $this->input->id,
                'content' => null,
                'type' => $this->outputType ?? 'ai_generated',
                'ai_model' => null,
                'quality_score' => 0,
                'metadata' => [
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'failed_at' => now()->toISOString(),
                    'attempts' => $this->attempts()
                ]
            ]);

            $this->input->update(['status' => 'failed']);
        }

        throw $e;
    }

    /**
     * Handle general exceptions
     */
    protected function handleGeneralException(Exception $e): void
    {
        Log::error("General error in AI content generation", [
            'input_id' => $this->input->id,
            'output_type' => $this->outputType,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'attempt' => $this->attempts()
        ]);

        if ($this->attempts() >= $this->tries) {
            $this->input->update(['status' => 'failed']);
        }

        throw $e;
    }

    /**
     * Dispatch follow-up jobs based on generated content
     */
    protected function dispatchFollowupJobs(Output $output, array $generatedContent): void
    {
        // Generate vector embeddings for the output
        if ($this->shouldGenerateEmbeddings()) {
            GenerateVectorEmbeddingsJob::dispatch($output)
                ->onQueue('low-priority')
                ->delay(now()->addSeconds(30)); // Small delay to avoid overwhelming the system
        }

        // Process feedback learning if enabled
        if ($this->shouldProcessFeedbackLearning()) {
            ProcessFeedbackLearningJob::dispatch($output)
                ->onQueue('low-priority')
                ->delay(now()->addMinutes(5)); // Delay to allow time for user feedback
        }
    }

    /**
     * Determine if embeddings should be generated
     */
    protected function shouldGenerateEmbeddings(): bool
    {
        return isset($this->options['generate_embeddings'])
            && $this->options['generate_embeddings'] === true;
    }

    /**
     * Determine if feedback learning should be processed
     */
    protected function shouldProcessFeedbackLearning(): bool
    {
        return isset($this->options['enable_feedback_learning'])
            && $this->options['enable_feedback_learning'] === true;
    }

    /**
     * Determine queue priority
     */
    protected function determineQueuePriority(): string
    {
        // High priority for real-time requests
        if (isset($this->options['priority']) && $this->options['priority'] === 'high') {
            return 'high-priority';
        }

        // High priority for paid users
        $user = $this->input->user ?? null;
        if ($user && method_exists($user, 'isPaid') && $user->isPaid()) {
            return 'high-priority';
        }

        // Medium priority for standard requests
        return 'medium-priority';
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
        Log::error("AI content generation job failed permanently", [
            'input_id' => $this->input->id,
            'output_type' => $this->outputType,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Update input status
        $this->input->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'failed_at' => now()
        ]);

        // Create failed output record for tracking
        Output::create([
            'input_id' => $this->input->id,
            'content' => null,
            'type' => $this->outputType ?? 'ai_generated',
            'ai_model' => null,
            'quality_score' => 0,
            'metadata' => [
                'permanent_failure' => true,
                'error' => $exception->getMessage(),
                'failed_at' => now()->toISOString(),
                'total_attempts' => $this->attempts()
            ]
        ]);
    }
}