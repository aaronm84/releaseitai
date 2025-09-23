<?php

namespace App\Observers;

use App\Models\Feedback;
use App\Services\Cache\CacheInvalidationService;
use Illuminate\Support\Facades\Log;

/**
 * FeedbackCacheObserver - Automatically invalidates feedback and RAG-related caches
 *
 * This observer listens to Feedback model events and triggers appropriate
 * cache invalidation for feedback patterns, similarity searches, and user preferences.
 */
class FeedbackCacheObserver
{
    private CacheInvalidationService $cacheInvalidation;

    public function __construct(CacheInvalidationService $cacheInvalidation)
    {
        $this->cacheInvalidation = $cacheInvalidation;
    }

    /**
     * Handle the Feedback "created" event.
     */
    public function created(Feedback $feedback): void
    {
        try {
            $invalidatedCount = $this->cacheInvalidation->invalidateFeedbackCaches($feedback);

            Log::debug('Feedback cache invalidation on creation', [
                'feedback_id' => $feedback->id,
                'user_id' => $feedback->user_id,
                'output_id' => $feedback->output_id,
                'action' => $feedback->action,
                'invalidated_count' => $invalidatedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Feedback cache invalidation failed on creation', [
                'feedback_id' => $feedback->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Feedback "updated" event.
     */
    public function updated(Feedback $feedback): void
    {
        try {
            // For feedback updates, we need to invalidate both old and new state caches
            $invalidatedCount = $this->cacheInvalidation->invalidateFeedbackCaches($feedback);

            // If confidence changed significantly, invalidate quality score caches
            if ($feedback->wasChanged('confidence')) {
                $oldConfidence = $feedback->getOriginal('confidence');
                $newConfidence = $feedback->confidence;

                if (abs($oldConfidence - $newConfidence) > 0.2) {
                    // Significant confidence change - invalidate quality caches more broadly
                    $this->cacheInvalidation->bulkInvalidate(['feedback']);
                }
            }

            Log::debug('Feedback cache invalidation on update', [
                'feedback_id' => $feedback->id,
                'changes' => array_keys($feedback->getChanges()),
                'invalidated_count' => $invalidatedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Feedback cache invalidation failed on update', [
                'feedback_id' => $feedback->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Feedback "deleted" event.
     */
    public function deleted(Feedback $feedback): void
    {
        try {
            $invalidatedCount = $this->cacheInvalidation->invalidateFeedbackCaches($feedback);

            // When feedback is deleted, we need to invalidate analytics more broadly
            $this->cacheInvalidation->bulkInvalidate(['feedback']);

            Log::debug('Feedback cache invalidation on deletion', [
                'feedback_id' => $feedback->id,
                'user_id' => $feedback->user_id,
                'invalidated_count' => $invalidatedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Feedback cache invalidation failed on deletion', [
                'feedback_id' => $feedback->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Feedback "restored" event.
     */
    public function restored(Feedback $feedback): void
    {
        // Treat restoration similar to creation
        $this->created($feedback);
    }

    /**
     * Handle the Feedback "force deleted" event.
     */
    public function forceDeleted(Feedback $feedback): void
    {
        // More aggressive invalidation for force deletion
        try {
            $this->cacheInvalidation->invalidateFeedbackCaches($feedback);
            $this->cacheInvalidation->bulkInvalidate(['feedback', 'embeddings']);

            Log::debug('Feedback cache force invalidation', [
                'feedback_id' => $feedback->id
            ]);

        } catch (\Exception $e) {
            Log::error('Feedback cache force invalidation failed', [
                'feedback_id' => $feedback->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}