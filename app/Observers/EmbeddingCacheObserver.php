<?php

namespace App\Observers;

use App\Models\Embedding;
use App\Services\Cache\CacheInvalidationService;
use Illuminate\Support\Facades\Log;

/**
 * EmbeddingCacheObserver - Automatically invalidates embedding and similarity caches
 *
 * This observer listens to Embedding model events and triggers appropriate
 * cache invalidation for vector similarity searches and RAG functionality.
 */
class EmbeddingCacheObserver
{
    private CacheInvalidationService $cacheInvalidation;

    public function __construct(CacheInvalidationService $cacheInvalidation)
    {
        $this->cacheInvalidation = $cacheInvalidation;
    }

    /**
     * Handle the Embedding "created" event.
     */
    public function created(Embedding $embedding): void
    {
        try {
            $invalidatedCount = $this->cacheInvalidation->invalidateEmbeddingCaches(
                $embedding->content_type,
                $embedding->content_id
            );

            Log::debug('Embedding cache invalidation on creation', [
                'embedding_id' => $embedding->id,
                'content_type' => $embedding->content_type,
                'content_id' => $embedding->content_id,
                'invalidated_count' => $invalidatedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Embedding cache invalidation failed on creation', [
                'embedding_id' => $embedding->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Embedding "updated" event.
     */
    public function updated(Embedding $embedding): void
    {
        try {
            // Vector updates significantly affect similarity searches
            if ($embedding->wasChanged('vector')) {
                // Invalidate all similarity caches when vector changes
                $this->cacheInvalidation->bulkInvalidate(['embeddings']);

                Log::info('Bulk embedding cache invalidation due to vector change', [
                    'embedding_id' => $embedding->id,
                    'content_type' => $embedding->content_type,
                    'content_id' => $embedding->content_id
                ]);
            } else {
                // For other changes, just invalidate specific caches
                $invalidatedCount = $this->cacheInvalidation->invalidateEmbeddingCaches(
                    $embedding->content_type,
                    $embedding->content_id
                );

                Log::debug('Embedding cache invalidation on update', [
                    'embedding_id' => $embedding->id,
                    'changes' => array_keys($embedding->getChanges()),
                    'invalidated_count' => $invalidatedCount
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Embedding cache invalidation failed on update', [
                'embedding_id' => $embedding->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Embedding "deleted" event.
     */
    public function deleted(Embedding $embedding): void
    {
        try {
            // When an embedding is deleted, invalidate all similarity caches
            // as the similarity rankings for all other embeddings may change
            $this->cacheInvalidation->bulkInvalidate(['embeddings']);

            Log::debug('Embedding cache invalidation on deletion', [
                'embedding_id' => $embedding->id,
                'content_type' => $embedding->content_type,
                'content_id' => $embedding->content_id
            ]);

        } catch (\Exception $e) {
            Log::error('Embedding cache invalidation failed on deletion', [
                'embedding_id' => $embedding->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Embedding "restored" event.
     */
    public function restored(Embedding $embedding): void
    {
        // Treat restoration similar to creation
        $this->created($embedding);
    }

    /**
     * Handle the Embedding "force deleted" event.
     */
    public function forceDeleted(Embedding $embedding): void
    {
        // Same as regular deletion - invalidate all similarity caches
        $this->deleted($embedding);
    }
}