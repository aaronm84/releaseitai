<?php

namespace App\Services;

use App\Models\Workstream;
use App\Models\WorkstreamPermission;
use App\Traits\DistributedCacheable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service class for handling workstream-related business logic.
 *
 * This service provides a clean abstraction layer between controllers and models,
 * encapsulating complex business rules for workstream management, hierarchy operations,
 * permission handling, and bulk operations.
 *
 * @package App\Services
 */
class WorkstreamService
{
    use DistributedCacheable;
    /**
     * Get paginated workstreams with filtering options.
     *
     * Retrieves a paginated list of workstreams with optional filtering by type,
     * status, and parent relationship. Includes performance optimizations with
     * eager loading of essential relationships.
     *
     * @param array $filters Associative array of filters:
     *                      - 'type': Filter by workstream type (product_line, initiative, experiment)
     *                      - 'status': Filter by status (draft, active, on_hold, completed, cancelled)
     *                      - 'parent_workstream_id': Filter by parent workstream ID ('null' for root workstreams)
     * @param int $perPage Number of items per page (max 100, default 50)
     * @return LengthAwarePaginator Paginated workstream collection with relationships
     */
    public function getWorkstreams(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = Workstream::withBulkEssentials();

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['parent_workstream_id'])) {
            if ($filters['parent_workstream_id'] === 'null') {
                $query->whereNull('parent_workstream_id');
            } else {
                $query->where('parent_workstream_id', $filters['parent_workstream_id']);
            }
        }

        // Add performance-optimized pagination with essential relationships
        $perPage = min($perPage, 100); // Max 100 items per page

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Create a new workstream.
     *
     * Creates a new workstream with automatic hierarchy depth calculation
     * and validation. Includes hierarchy depth validation to prevent
     * exceeding maximum depth limits.
     *
     * @param array $data Workstream data including name, type, owner_id, etc.
     * @return Workstream The created workstream with loaded relationships
     * @throws \InvalidArgumentException If hierarchy depth would exceed maximum
     */
    public function createWorkstream(array $data): Workstream
    {
        // Additional validation for hierarchy depth
        if (isset($data['parent_workstream_id'])) {
            $parent = Workstream::find($data['parent_workstream_id']);
            if ($parent && $parent->getHierarchyDepth() >= Workstream::MAX_HIERARCHY_DEPTH) {
                throw new \InvalidArgumentException('Workstream hierarchy cannot exceed 3 levels deep.');
            }
        }

        $workstream = Workstream::create($data);
        $workstream->load(['owner', 'parentWorkstream']);

        return $workstream;
    }

    /**
     * Get a workstream with access check.
     *
     * Retrieves a workstream only if the current user has the required permission level.
     * Loads essential relationships for the response.
     *
     * @param Workstream $workstream The workstream to retrieve
     * @param string $permissionType Required permission level ('view', 'edit', 'admin')
     * @return Workstream|null The workstream with relationships or null if access denied
     */
    public function getWorkstream(Workstream $workstream, string $permissionType = 'view'): ?Workstream
    {
        if (!$this->userCanAccessWorkstream($workstream, $permissionType)) {
            return null;
        }

        // Use optimized loading with essential relationships
        $workstream->load(['owner:id,name,email', 'parentWorkstream:id,name,type,hierarchy_depth']);
        return $workstream;
    }

    /**
     * Update a workstream with access check.
     */
    public function updateWorkstream(Workstream $workstream, array $data): ?Workstream
    {
        if (!$this->userCanAccessWorkstream($workstream, 'edit')) {
            return null;
        }

        $workstream->update($data);
        $workstream->load(['owner:id,name,email', 'parentWorkstream:id,name,type,hierarchy_depth']);

        return $workstream;
    }

    /**
     * Delete a workstream if it can be deleted.
     */
    public function deleteWorkstream(Workstream $workstream): bool
    {
        if (!$workstream->canBeDeleted()) {
            return false;
        }

        return $workstream->delete();
    }

    /**
     * Get hierarchy tree for a workstream.
     */
    public function getHierarchy(Workstream $workstream): ?array
    {
        if (!$this->userCanAccessWorkstream($workstream, 'view')) {
            return null;
        }

        return $workstream->buildHierarchyTree();
    }

    /**
     * Get rollup report for a workstream.
     */
    public function getRollupReport(Workstream $workstream): ?array
    {
        if (!$this->userCanAccessWorkstream($workstream, 'view')) {
            return null;
        }

        return $workstream->getRollupReport();
    }

    /**
     * Get permissions for a workstream.
     */
    public function getPermissions(Workstream $workstream): ?array
    {
        if (!$this->userCanAccessWorkstream($workstream, 'view')) {
            return null;
        }

        $userId = Auth::id();
        $permissions = $workstream->getEffectivePermissionsForUser($userId);

        return ['user_permissions' => $permissions];
    }

    /**
     * Grant permissions on a workstream.
     */
    public function grantPermissions(Workstream $workstream, array $data): ?WorkstreamPermission
    {
        // Check if current user can grant permissions
        $canGrant = $this->userCanAccessWorkstream($workstream, 'admin') ||
                   $this->userOwnsParentWorkstream($workstream);

        if (!$canGrant) {
            return null;
        }

        return WorkstreamPermission::updateOrCreate(
            [
                'workstream_id' => $workstream->id,
                'user_id' => $data['user_id'],
                'permission_type' => $data['permission_type'],
            ],
            [
                'scope' => $data['scope'] ?? 'workstream_only',
                'granted_by' => Auth::id(),
            ]
        );
    }

    /**
     * Move a workstream to a new parent.
     */
    public function moveWorkstream(Workstream $workstream, ?int $newParentId): array
    {
        // Validate hierarchy constraints
        if ($newParentId) {
            // Check for circular hierarchy
            if ($workstream->wouldCreateCircularHierarchy($newParentId)) {
                return [
                    'success' => false,
                    'error' => 'Cannot create circular workstream relationship.'
                ];
            }

            // Check depth limit
            $newParent = Workstream::find($newParentId);
            if ($newParent->getHierarchyDepth() >= Workstream::MAX_HIERARCHY_DEPTH) {
                return [
                    'success' => false,
                    'error' => 'Workstream hierarchy cannot exceed 3 levels deep.'
                ];
            }
        }

        $workstream->update(['parent_workstream_id' => $newParentId]);
        $workstream->load(['parentWorkstream']);

        return [
            'success' => true,
            'workstream' => $workstream
        ];
    }

    /**
     * Bulk update workstreams.
     */
    public function bulkUpdateWorkstreams(array $workstreamIds, array $updates): array
    {
        $chunkSize = 100;
        $totalUpdated = 0;
        $updatedWorkstreams = [];

        DB::transaction(function () use ($workstreamIds, $updates, $chunkSize, &$totalUpdated, &$updatedWorkstreams) {
            collect($workstreamIds)->chunk($chunkSize)->each(function ($chunk) use ($updates, &$totalUpdated, &$updatedWorkstreams) {
                // Use optimized bulk loading for workstreams
                $workstreams = Workstream::forBulkOperations()
                    ->whereIn('id', $chunk->toArray())
                    ->get();

                foreach ($workstreams as $workstream) {
                    $workstream->update($updates);
                    $updatedWorkstreams[] = [
                        'id' => $workstream->id,
                        'status' => $workstream->status,
                    ];
                    $totalUpdated++;
                }
            });
        });

        return [
            'updated_count' => $totalUpdated,
            'updated_workstreams' => $updatedWorkstreams,
        ];
    }

    /**
     * Check if the current user can access a workstream with the given permission.
     *
     * Implements hierarchical permission checking with inheritance from parent workstreams.
     * Owners always have full access regardless of explicit permissions.
     *
     * @param Workstream $workstream The workstream to check access for
     * @param string $permissionType Required permission level ('view', 'edit', 'admin')
     * @return bool True if user has required access, false otherwise
     */
    public function userCanAccessWorkstream(Workstream $workstream, string $permissionType): bool
    {
        $userId = Auth::id();

        // Owner always has access
        if ($workstream->owner_id === $userId) {
            return true;
        }

        // Cache permission checks for performance using distributed cache
        $cacheKey = $this->buildDistributedCacheKey('user_permissions_check', [
            'workstream_id' => $workstream->id,
            'user_id' => $userId,
            'type' => $permissionType
        ]);

        return $this->cachePermissionData($cacheKey, function () use ($workstream, $userId, $permissionType) {
            // Get effective permissions for the user
            $effectivePermissions = $workstream->getEffectivePermissionsForUser($userId);

            // Check if user has the required permission or higher
            $permissionHierarchy = ['view' => 1, 'edit' => 2, 'admin' => 3];
            $requiredLevel = $permissionHierarchy[$permissionType] ?? 0;

            foreach ($effectivePermissions['effective_permissions'] as $permission) {
                $userLevel = $permissionHierarchy[$permission] ?? 0;
                if ($userLevel >= $requiredLevel) {
                    return true;
                }
            }

            return false;
        }, $userId, ["workstream:{$workstream->id}"]);
    }

    /**
     * Check if the current user owns a parent workstream in the hierarchy.
     */
    private function userOwnsParentWorkstream(Workstream $workstream): bool
    {
        $userId = Auth::id();
        $ancestors = $workstream->getAllAncestors();

        foreach ($ancestors as $ancestor) {
            if ($ancestor->owner_id === $userId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get multiple workstreams with their children efficiently.
     */
    public function getWorkstreamsWithChildren(array $workstreamIds): Collection
    {
        return Workstream::whereIn('id', $workstreamIds)
            ->withCompleteHierarchy()
            ->get();
    }

    /**
     * Get multiple workstreams with their owners efficiently.
     */
    public function getWorkstreamsWithOwners(array $workstreamIds): Collection
    {
        return Workstream::whereIn('id', $workstreamIds)
            ->with(['owner:id,name,email'])
            ->get();
    }

    /**
     * Get workstreams with permission context for bulk operations.
     */
    public function getWorkstreamsWithPermissions(array $workstreamIds, int $userId): Collection
    {
        return Workstream::whereIn('id', $workstreamIds)
            ->withPermissions($userId)
            ->withBulkEssentials()
            ->get();
    }

    /**
     * Search workstreams with hierarchy context efficiently.
     */
    public function searchWorkstreamsWithHierarchy(string $searchTerm, array $filters = []): Collection
    {
        return Workstream::searchWithHierarchyContext($searchTerm, $filters);
    }

    /**
     * Get hierarchy trees for multiple root workstreams efficiently.
     */
    public function getHierarchyTreesBulk(array $rootWorkstreamIds): Collection
    {
        return Workstream::loadHierarchyTreesBulk($rootWorkstreamIds);
    }

    /**
     * Get workstreams with release summary data efficiently.
     */
    public function getWorkstreamsWithReleaseSummary(array $filters = []): Collection
    {
        $query = Workstream::withReleaseSummary();

        // Apply filters
        if (isset($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['hierarchy_depth'])) {
            $query->atDepth($filters['hierarchy_depth']);
        }

        return $query->get();
    }
}