<?php

namespace App\Observers;

use App\Models\Workstream;
use App\Services\Cache\CacheInvalidationService;
use Illuminate\Support\Facades\Log;

/**
 * WorkstreamCacheObserver - Automatically invalidates workstream-related caches
 *
 * This observer listens to Workstream model events and triggers appropriate
 * cache invalidation to maintain consistency across distributed servers.
 */
class WorkstreamCacheObserver
{
    private CacheInvalidationService $cacheInvalidation;

    public function __construct(CacheInvalidationService $cacheInvalidation)
    {
        $this->cacheInvalidation = $cacheInvalidation;
    }

    /**
     * Handle the Workstream "created" event.
     */
    public function created(Workstream $workstream): void
    {
        $this->invalidateHierarchyCaches($workstream, ['created']);

        // If workstream has a parent, invalidate parent's hierarchy caches
        if ($workstream->parent_workstream_id) {
            $parent = Workstream::find($workstream->parent_workstream_id);
            if ($parent) {
                $this->invalidateHierarchyCaches($parent, ['child_added']);
            }
        }
    }

    /**
     * Handle the Workstream "updated" event.
     */
    public function updated(Workstream $workstream): void
    {
        $changes = [];
        $oldParentId = null;

        // Detect hierarchy changes
        if ($workstream->wasChanged('parent_workstream_id')) {
            $changes[] = 'hierarchy';
            $changes[] = 'moved';
            $changes[] = 'parent_changed';
            $oldParentId = $workstream->getOriginal('parent_workstream_id');
        }

        // Detect status changes
        if ($workstream->wasChanged('status')) {
            $changes[] = 'status_changed';
        }

        // Detect permission-affecting changes
        if ($workstream->wasChanged(['owner_id', 'type'])) {
            $changes[] = 'permissions_changed';
        }

        // General update
        if (empty($changes)) {
            $changes[] = 'updated';
        }

        // Include old parent ID in changes for proper invalidation
        if ($oldParentId) {
            $changes['old_parent_id'] = $oldParentId;
        }

        $this->invalidateHierarchyCaches($workstream, $changes);

        // If parent changed, invalidate old parent's caches
        if ($oldParentId) {
            $oldParent = Workstream::find($oldParentId);
            if ($oldParent) {
                $this->invalidateHierarchyCaches($oldParent, ['child_removed']);
            }
        }

        // If workstream has a current parent, invalidate parent's caches
        if ($workstream->parent_workstream_id && $workstream->parent_workstream_id !== $oldParentId) {
            $newParent = Workstream::find($workstream->parent_workstream_id);
            if ($newParent) {
                $this->invalidateHierarchyCaches($newParent, ['child_added']);
            }
        }
    }

    /**
     * Handle the Workstream "deleted" event.
     */
    public function deleted(Workstream $workstream): void
    {
        $this->invalidateHierarchyCaches($workstream, ['deleted']);

        // If workstream had a parent, invalidate parent's hierarchy caches
        if ($workstream->parent_workstream_id) {
            $parent = Workstream::find($workstream->parent_workstream_id);
            if ($parent) {
                $this->invalidateHierarchyCaches($parent, ['child_removed']);
            }
        }

        // Invalidate all permission caches for this workstream
        $this->cacheInvalidation->invalidateUserPermissions(0, [$workstream->id]);
    }

    /**
     * Handle the Workstream "restored" event.
     */
    public function restored(Workstream $workstream): void
    {
        $this->invalidateHierarchyCaches($workstream, ['restored']);

        // If workstream has a parent, invalidate parent's hierarchy caches
        if ($workstream->parent_workstream_id) {
            $parent = Workstream::find($workstream->parent_workstream_id);
            if ($parent) {
                $this->invalidateHierarchyCaches($parent, ['child_restored']);
            }
        }
    }

    /**
     * Handle the Workstream "force deleted" event.
     */
    public function forceDeleted(Workstream $workstream): void
    {
        // Same as deleted but more aggressive invalidation
        $this->deleted($workstream);

        // Also invalidate any stale references
        $this->cacheInvalidation->bulkInvalidate(['workstream']);
    }

    /**
     * Invalidate hierarchy caches with error handling
     *
     * @param Workstream $workstream
     * @param array $changes
     */
    private function invalidateHierarchyCaches(Workstream $workstream, array $changes): void
    {
        try {
            $invalidatedCount = $this->cacheInvalidation->invalidateWorkstreamHierarchy($workstream, $changes);

            Log::debug('Workstream cache invalidation triggered', [
                'workstream_id' => $workstream->id,
                'changes' => $changes,
                'invalidated_count' => $invalidatedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Workstream cache invalidation failed in observer', [
                'workstream_id' => $workstream->id,
                'changes' => $changes,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}