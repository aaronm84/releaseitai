<?php

namespace App\Jobs;

use App\Models\Content;
use App\Services\AiEntityDetectionService;
use App\Services\BrainDumpProcessor;
use App\Exceptions\AiServiceException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class ProcessBrainDumpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;
    public $maxExceptions = 1;
    public $backoff = [30, 60, 120]; // Exponential backoff

    protected Content $content;
    protected array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(Content $content, array $options = [])
    {
        $this->content = $content;
        $this->options = $options;

        // Set queue priority based on user plan or content type
        $this->onQueue($this->determineQueuePriority());
    }

    /**
     * Execute the job.
     */
    public function handle(
        AiEntityDetectionService $aiEntityDetectionService,
        BrainDumpProcessor $brainDumpProcessor
    ): void {
        $lockKey = "process_brain_dump_{$this->content->id}";

        // Prevent duplicate processing
        if (!Cache::lock($lockKey, 600)->get()) {
            Log::warning("Brain dump processing already in progress", [
                'content_id' => $this->content->id
            ]);
            return;
        }

        try {
            Log::info("Starting brain dump processing", [
                'content_id' => $this->content->id,
                'user_id' => $this->content->user_id,
                'attempt' => $this->attempts()
            ]);

            // Update status to processing
            $this->content->update(['status' => 'processing']);

            // Extract entities using AI
            $entities = $aiEntityDetectionService->extractEntities(
                $this->content->content,
                $this->options
            );

            // Process the brain dump with extracted entities
            $result = $brainDumpProcessor->process($this->content, $entities);

            // Update content with processed data
            $this->content->update([
                'status' => 'completed',
                'processed_at' => now(),
                'metadata' => array_merge(
                    $this->content->metadata ?? [],
                    [
                        'entities' => $entities,
                        'processing_result' => $result,
                        'processed_by_job' => true
                    ]
                )
            ]);

            // Dispatch follow-up jobs if needed
            $this->dispatchFollowupJobs($result);

            Log::info("Brain dump processing completed successfully", [
                'content_id' => $this->content->id,
                'entities_found' => count($entities),
                'processing_time' => microtime(true) - LARAVEL_START
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
        Log::error("AI service error in brain dump processing", [
            'content_id' => $this->content->id,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'attempt' => $this->attempts()
        ]);

        // Update content status on final failure
        if ($this->attempts() >= $this->tries) {
            $this->content->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }

        // Re-throw for retry logic
        throw $e;
    }

    /**
     * Handle general exceptions
     */
    protected function handleGeneralException(Exception $e): void
    {
        Log::error("General error in brain dump processing", [
            'content_id' => $this->content->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'attempt' => $this->attempts()
        ]);

        // Update content status on final failure
        if ($this->attempts() >= $this->tries) {
            $this->content->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }

        throw $e;
    }

    /**
     * Dispatch follow-up jobs based on processing result
     */
    protected function dispatchFollowupJobs(array $result): void
    {
        // Generate vector embeddings if needed
        if ($this->shouldGenerateEmbeddings($result)) {
            GenerateVectorEmbeddingsJob::dispatch($this->content)
                ->onQueue('medium-priority');
        }

        // Process any action items found
        if (isset($result['action_items']) && !empty($result['action_items'])) {
            // Dispatch action item processing jobs
            foreach ($result['action_items'] as $actionItem) {
                // Process action items (implementation depends on your action item system)
            }
        }
    }

    /**
     * Determine if embeddings should be generated
     */
    protected function shouldGenerateEmbeddings(array $result): bool
    {
        return isset($this->options['generate_embeddings'])
            && $this->options['generate_embeddings'] === true;
    }

    /**
     * Determine queue priority based on content and user
     */
    protected function determineQueuePriority(): string
    {
        // High priority for paid users or urgent content
        $user = $this->content->user ?? null;
        if (($user && method_exists($user, 'isPaid') && $user->isPaid()) || $this->isUrgentContent()) {
            return 'high-priority';
        }

        // Medium priority for regular content
        return 'medium-priority';
    }

    /**
     * Check if content is urgent
     */
    protected function isUrgentContent(): bool
    {
        return isset($this->options['urgent']) && $this->options['urgent'] === true;
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
        Log::error("Brain dump job failed permanently", [
            'content_id' => $this->content->id,
            'user_id' => $this->content->user_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Update content status
        $this->content->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'failed_at' => now()
        ]);

        // Notify user or administrators if needed
        // $this->notifyFailure($exception);
    }
}