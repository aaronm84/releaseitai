<?php

namespace App\Jobs;

use App\Models\Embedding;
use App\Models\Content;
use App\Models\Input;
use App\Models\Output;
use App\Services\AiService;
use App\Exceptions\AiServiceException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Exception;

class GenerateVectorEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;
    public $maxExceptions = 1;
    public $backoff = [30, 90, 180];

    protected Model $model;
    protected array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(Model $model, array $options = [])
    {
        $this->model = $model;
        $this->options = $options;

        // Always use low priority for embeddings unless specified
        $this->onQueue($this->options['queue'] ?? 'low-priority');
    }

    /**
     * Execute the job.
     */
    public function handle(AiService $aiService): void
    {
        $lockKey = "generate_embeddings_{$this->getModelKey()}";

        // Prevent duplicate processing
        if (!Cache::lock($lockKey, 600)->get()) {
            Log::warning("Vector embedding generation already in progress", [
                'model_type' => get_class($this->model),
                'model_id' => $this->model->id
            ]);
            return;
        }

        try {
            Log::info("Starting vector embedding generation", [
                'model_type' => get_class($this->model),
                'model_id' => $this->model->id,
                'attempt' => $this->attempts()
            ]);

            // Check if embeddings already exist
            if ($this->embeddingExists() && !$this->options['force_regenerate']) {
                Log::info("Embeddings already exist, skipping generation", [
                    'model_type' => get_class($this->model),
                    'model_id' => $this->model->id
                ]);
                return;
            }

            // Extract content to embed
            $contentToEmbed = $this->extractContentForEmbedding();

            if (empty($contentToEmbed)) {
                Log::warning("No content to embed", [
                    'model_type' => get_class($this->model),
                    'model_id' => $this->model->id
                ]);
                return;
            }

            // Generate embeddings using AI service
            $startTime = microtime(true);
            $embeddingData = $aiService->generateEmbedding(
                $contentToEmbed,
                $this->options
            );
            $processingTime = microtime(true) - $startTime;

            // Store or update embedding
            $embedding = $this->storeEmbedding($embeddingData, $processingTime);

            Log::info("Vector embedding generation completed successfully", [
                'model_type' => get_class($this->model),
                'model_id' => $this->model->id,
                'embedding_id' => $embedding->id,
                'processing_time' => $processingTime,
                'vector_dimensions' => count($embeddingData['vector']),
                'cost_usd' => $embeddingData['cost_usd'] ?? 0
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
     * Extract content for embedding based on model type
     */
    protected function extractContentForEmbedding(): string
    {
        switch (get_class($this->model)) {
            case Content::class:
                return $this->model->content ?? '';

            case Input::class:
                return $this->model->content ?? '';

            case Output::class:
                return $this->model->content ?? '';

            default:
                // For other models, try to find a content field
                if (isset($this->model->content)) {
                    return $this->model->content;
                } elseif (isset($this->model->title)) {
                    return $this->model->title;
                } elseif (isset($this->model->description)) {
                    return $this->model->description;
                }

                return '';
        }
    }

    /**
     * Check if embedding already exists
     */
    protected function embeddingExists(): bool
    {
        return Embedding::where('content_id', $this->model->id)
            ->where('content_type', get_class($this->model))
            ->where('model', $this->options['model'] ?? 'text-embedding-ada-002')
            ->exists();
    }

    /**
     * Store the embedding in the database
     */
    protected function storeEmbedding(array $embeddingData, float $processingTime): Embedding
    {
        // Delete existing embedding if regenerating
        if ($this->options['force_regenerate'] ?? false) {
            Embedding::where('content_id', $this->model->id)
                ->where('content_type', get_class($this->model))
                ->where('model', $this->options['model'] ?? 'text-embedding-ada-002')
                ->delete();
        }

        return Embedding::create([
            'content_id' => $this->model->id,
            'content_type' => get_class($this->model),
            'vector' => '[' . implode(',', $embeddingData['vector']) . ']',
            'model' => $embeddingData['model'] ?? 'text-embedding-ada-002',
            'dimensions' => count($embeddingData['vector']),
            'metadata' => [
                'processing_time_seconds' => $processingTime,
                'tokens_used' => $embeddingData['tokens_used'] ?? null,
                'cost_usd' => $embeddingData['cost_usd'] ?? null,
                'generated_by_job' => true,
                'job_id' => $this->job->getJobId(),
                'options' => $this->options
            ]
        ]);
    }

    /**
     * Get a unique key for this model
     */
    protected function getModelKey(): string
    {
        return get_class($this->model) . '_' . $this->model->id;
    }

    /**
     * Handle AI service specific exceptions
     */
    protected function handleAiServiceException(AiServiceException $e): void
    {
        Log::error("AI service error in vector embedding generation", [
            'model_type' => get_class($this->model),
            'model_id' => $this->model->id,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'attempt' => $this->attempts()
        ]);

        // Store failed embedding attempt on final failure
        if ($this->attempts() >= $this->tries) {
            Embedding::create([
                'content_id' => $this->model->id,
                'content_type' => get_class($this->model),
                'vector' => null,
                'model' => $this->options['model'] ?? 'text-embedding-ada-002',
                'dimensions' => 0,
                'metadata' => [
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'failed_at' => now()->toISOString(),
                    'attempts' => $this->attempts()
                ]
            ]);
        }

        throw $e;
    }

    /**
     * Handle general exceptions
     */
    protected function handleGeneralException(Exception $e): void
    {
        Log::error("General error in vector embedding generation", [
            'model_type' => get_class($this->model),
            'model_id' => $this->model->id,
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
        Log::error("Vector embedding generation job failed permanently", [
            'model_type' => get_class($this->model),
            'model_id' => $this->model->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Create failed embedding record for tracking
        Embedding::create([
            'content_id' => $this->model->id,
            'content_type' => get_class($this->model),
            'vector' => null,
            'model' => $this->options['model'] ?? 'text-embedding-ada-002',
            'dimensions' => 0,
            'metadata' => [
                'permanent_failure' => true,
                'error' => $exception->getMessage(),
                'failed_at' => now()->toISOString(),
                'total_attempts' => $this->attempts()
            ]
        ]);
    }
}