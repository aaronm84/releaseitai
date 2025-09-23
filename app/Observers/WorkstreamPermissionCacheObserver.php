<?php

namespace App\Observers;

use App\Models\WorkstreamPermission;
use App\Services\Cache\CacheInvalidationService;
use Illuminate\Support\Facades\Log;

/**
 * WorkstreamPermissionCacheObserver - Automatically invalidates permission-related caches
 *
 * This observer listens to WorkstreamPermission model events and triggers appropriate
 * cache invalidation for user permissions and authorization checks.
 */
class WorkstreamPermissionCacheObserver
{
    private CacheInvalidationService $cacheInvalidation;

    public function __construct(CacheInvalidationService $cacheInvalidation)
    {
        $this->cacheInvalidation = $cacheInvalidation;
    }

    /**
     * Handle the WorkstreamPermission "created" event.
     */
    public function created(WorkstreamPermission $permission): void
    {
        try {
            $invalidatedCount = $this->cacheInvalidation->invalidateUserPermissions(
                $permission->user_id,
                [$permission->workstream_id]
            );

            // If permission scope affects children, invalidate child workstreams too
            if ($permission->scope === 'workstream_and_children') {
                $this->invalidateChildWorkstreamPermissions($permission);
            }

            Log::debug('Permission cache invalidation on creation', [
                'permission_id' => $permission->id,
                'user_id' => $permission->user_id,
                'workstream_id' => $permission->workstream_id,
                'permission_type' => $permission->permission_type,
                'scope' => $permission->scope,
                'invalidated_count' => $invalidatedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Permission cache invalidation failed on creation', [
                'permission_id' => $permission->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the WorkstreamPermission "updated" event.
     */
    public function updated(WorkstreamPermission $permission): void
    {
        try {
            $invalidatedCount = $this->cacheInvalidation->invalidateUserPermissions(
                $permission->user_id,
                [$permission->workstream_id]
            );

            // If scope changed, we need broader invalidation
            if ($permission->wasChanged('scope')) {
                $oldScope = $permission->getOriginal('scope');
                $newScope = $permission->scope;

                // If either old or new scope affects children, invalidate child permissions
                if ($oldScope === 'workstream_and_children' || $newScope === 'workstream_and_children') {
                    $this->invalidateChildWorkstreamPermissions($permission);
                }
            }

            // If permission type changed, invalidate more broadly
            if ($permission->wasChanged('permission_type')) {
                $this->cacheInvalidation->invalidateUserPermissions($permission->user_id);
            }

            Log::debug('Permission cache invalidation on update', [
                'permission_id' => $permission->id,
                'changes' => array_keys($permission->getChanges()),
                'invalidated_count' => $invalidatedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Permission cache invalidation failed on update', [
                'permission_id' => $permission->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the WorkstreamPermission "deleted" event.
     */
    public function deleted(WorkstreamPermission $permission): void
    {
        try {
            $invalidatedCount = $this->cacheInvalidation->invalidateUserPermissions(
                $permission->user_id,
                [$permission->workstream_id]
            );

            // If deleted permission had child scope, invalidate child workstream permissions
            if ($permission->scope === 'workstream_and_children') {
                $this->invalidateChildWorkstreamPermissions($permission);
            }

            Log::debug('Permission cache invalidation on deletion', [
                'permission_id' => $permission->id,
                'user_id' => $permission->user_id,
                'workstream_id' => $permission->workstream_id,
                'invalidated_count' => $invalidatedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Permission cache invalidation failed on deletion', [
                'permission_id' => $permission->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the WorkstreamPermission "restored" event.
     */
    public function restored(WorkstreamPermission $permission): void
    {
        // Treat restoration similar to creation
        $this->created($permission);
    }

    /**
     * Handle the WorkstreamPermission "force deleted" event.
     */
    public function forceDeleted(WorkstreamPermission $permission): void
    {
        // More aggressive invalidation for force deletion
        try {
            $this->cacheInvalidation->invalidateUserPermissions($permission->user_id);
            $this->cacheInvalidation->bulkInvalidate(['permissions']);

            Log::debug('Permission cache force invalidation', [
                'permission_id' => $permission->id,
                'user_id' => $permission->user_id
            ]);

        } catch (\Exception $e) {
            Log::error('Permission cache force invalidation failed', [
                'permission_id' => $permission->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Invalidate child workstream permissions when parent permission with child scope changes
     *
     * @param WorkstreamPermission $permission
     */
    private function invalidateChildWorkstreamPermissions(WorkstreamPermission $permission): void
    {
        try {
            // Get child workstreams
            $workstream = $permission->workstream;
            if ($workstream) {
                $childWorkstreams = $workstream->getAllDescendantsOptimized();
                $childWorkstreamIds = $childWorkstreams->pluck('id')->toArray();

                if (!empty($childWorkstreamIds)) {
                    $this->cacheInvalidation->invalidateUserPermissions(
                        $permission->user_id,
                        $childWorkstreamIds
                    );

                    Log::debug('Child workstream permissions invalidated', [
                        'parent_permission_id' => $permission->id,
                        'child_workstream_count' => count($childWorkstreamIds)
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Child workstream permission invalidation failed', [
                'permission_id' => $permission->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}