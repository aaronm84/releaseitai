<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

trait WorkstreamHierarchyOptimized
{
    /**
     * Get all descendants using optimized recursive CTE query.
     */
    public function getAllDescendantsOptimized(): Collection
    {
        $cacheKey = "workstream_descendants_{$this->id}";

        $results = Cache::remember($cacheKey, 300, function () {
            $results = DB::select("
                WITH RECURSIVE descendant_hierarchy AS (
                    -- Base case: direct children
                    SELECT id, parent_workstream_id, name, type, status, owner_id, hierarchy_depth
                    FROM workstreams
                    WHERE parent_workstream_id = ?

                    UNION ALL

                    -- Recursive case: children of children
                    SELECT w.id, w.parent_workstream_id, w.name, w.type, w.status, w.owner_id, w.hierarchy_depth
                    FROM workstreams w
                    INNER JOIN descendant_hierarchy dh ON w.parent_workstream_id = dh.id
                )
                SELECT * FROM descendant_hierarchy
                ORDER BY hierarchy_depth, id
            ", [$this->id]);

            return $results;
        });

        return static::hydrate($results);
    }

    /**
     * Get all ancestors using optimized recursive CTE query.
     */
    public function getAllAncestorsOptimized(): Collection
    {
        if (!$this->parent_workstream_id) {
            return new Collection();
        }

        $cacheKey = "workstream_ancestors_{$this->id}";

        $results = Cache::remember($cacheKey, 300, function () {
            $results = DB::select("
                WITH RECURSIVE ancestor_hierarchy AS (
                    -- Base case: direct parent
                    SELECT id, parent_workstream_id, name, type, status, owner_id, hierarchy_depth
                    FROM workstreams
                    WHERE id = ?

                    UNION ALL

                    -- Recursive case: parent of parent
                    SELECT w.id, w.parent_workstream_id, w.name, w.type, w.status, w.owner_id, w.hierarchy_depth
                    FROM workstreams w
                    INNER JOIN ancestor_hierarchy ah ON w.id = ah.parent_workstream_id
                )
                SELECT * FROM ancestor_hierarchy
                WHERE id != ?
                ORDER BY hierarchy_depth
            ", [$this->parent_workstream_id, $this->id]);

            return $results;
        });

        return static::hydrate($results);
    }

    /**
     * Get hierarchy depth efficiently using cached column.
     */
    public function getHierarchyDepthOptimized(): int
    {
        // Use cached hierarchy_depth column instead of traversing hierarchy
        return $this->hierarchy_depth ?? $this->calculateAndCacheHierarchyDepth();
    }

    /**
     * Calculate and cache hierarchy depth if not already set.
     */
    protected function calculateAndCacheHierarchyDepth(): int
    {
        $depth = DB::selectOne("
            WITH RECURSIVE depth_calculation AS (
                -- Base case: this workstream
                SELECT id, parent_workstream_id, 1 as depth
                FROM workstreams
                WHERE id = ?

                UNION ALL

                -- Recursive case: traverse up to root
                SELECT w.id, w.parent_workstream_id, dc.depth + 1 as depth
                FROM workstreams w
                INNER JOIN depth_calculation dc ON w.id = dc.parent_workstream_id
            )
            SELECT MAX(depth) as max_depth FROM depth_calculation
        ", [$this->id]);

        $calculatedDepth = $depth->max_depth ?? 1;

        // Update the cached depth value
        $this->update(['hierarchy_depth' => $calculatedDepth]);

        return $calculatedDepth;
    }

    /**
     * Build hierarchy tree with optimized eager loading.
     */
    public function buildHierarchyTreeOptimized(): array
    {
        $cacheKey = "workstream_tree_{$this->id}";

        return Cache::remember($cacheKey, 600, function () {
            // Get all descendants efficiently
            $allDescendants = $this->getAllDescendantsOptimized();

            // Load the owner for current workstream if not already loaded
            if (!$this->relationLoaded('owner')) {
                $this->load('owner:id,name,email');
            }

            // Build the tree structure efficiently
            return $this->buildTreeFromFlatList($allDescendants);
        });
    }

    /**
     * Build tree structure from flat list of descendants.
     */
    protected function buildTreeFromFlatList(Collection $descendants): array
    {
        // Create a map for quick lookups
        $workstreamMap = [];

        // Add root workstream to the map
        $workstreamMap[$this->id] = [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'owner' => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ] : null,
            'children' => [],
        ];

        // Add all descendants to the map
        foreach ($descendants as $descendant) {
            $workstreamMap[$descendant->id] = [
                'id' => $descendant->id,
                'name' => $descendant->name,
                'type' => $descendant->type,
                'status' => $descendant->status,
                'owner' => null, // Owner data not available from CTE query
                'children' => [],
            ];
        }

        // Build parent-child relationships efficiently
        foreach ($descendants as $descendant) {
            $parentId = $descendant->parent_workstream_id;
            if (isset($workstreamMap[$parentId])) {
                $workstreamMap[$parentId]['children'][] = $workstreamMap[$descendant->id];
            }
        }

        return $workstreamMap[$this->id];
    }

    /**
     * Check if setting a new parent would create circular hierarchy efficiently.
     */
    public function wouldCreateCircularHierarchyOptimized(?int $newParentId): bool
    {
        if (!$newParentId || $newParentId === $this->id) {
            return $newParentId === $this->id;
        }

        // Use optimized query to check if newParentId is in descendants
        $descendantIds = $this->getAllDescendantsOptimized()->pluck('id')->toArray();
        return in_array($newParentId, $descendantIds);
    }

    /**
     * Get effective permissions with optimized bulk queries.
     */
    public function getEffectivePermissionsForUserOptimized(int $userId): array
    {
        $cacheKey = "workstream_permissions_{$this->id}_{$userId}";

        return Cache::remember($cacheKey, 180, function () use ($userId) {
            // Get all ancestors in a single query
            $ancestorIds = $this->getAllAncestorsOptimized()->pluck('id')->toArray();
            $allWorkstreamIds = array_merge([$this->id], $ancestorIds);

            // Get all relevant permissions in a single query
            $allPermissions = DB::table('workstream_permissions')
                ->whereIn('workstream_id', $allWorkstreamIds)
                ->where('user_id', $userId)
                ->select('workstream_id', 'permission_type', 'scope')
                ->get()
                ->groupBy('workstream_id');

            // Process permissions efficiently
            $directPermissions = $allPermissions->get($this->id, collect())->keyBy('permission_type');
            $inheritedPermissions = collect();

            // Process inherited permissions from ancestors
            foreach ($ancestorIds as $ancestorId) {
                $ancestorPermissions = $allPermissions->get($ancestorId, collect())
                    ->where('scope', 'workstream_and_children');

                foreach ($ancestorPermissions as $permission) {
                    if (!$inheritedPermissions->has($permission->permission_type)) {
                        $ancestorWorkstream = $this->getAllAncestorsOptimized()->firstWhere('id', $ancestorId);
                        $inheritedPermissions->put($permission->permission_type, [
                            'permission_type' => $permission->permission_type,
                            'inherited_from_workstream_id' => $ancestorId,
                            'inherited_from_workstream_name' => $ancestorWorkstream->name ?? 'Unknown',
                        ]);
                    }
                }
            }

            // Calculate effective permissions
            $effectivePermissions = [];
            foreach (['view', 'edit', 'admin'] as $permType) {
                if ($directPermissions->has($permType) || $inheritedPermissions->has($permType)) {
                    $effectivePermissions[] = $permType;
                }
            }

            // Apply permission hierarchy
            if (in_array('admin', $effectivePermissions)) {
                $effectivePermissions = array_unique(array_merge($effectivePermissions, ['edit', 'view']));
            } elseif (in_array('edit', $effectivePermissions)) {
                $effectivePermissions = array_unique(array_merge($effectivePermissions, ['view']));
            }

            return [
                'workstream_id' => $this->id,
                'direct_permissions' => $directPermissions->values()->toArray(),
                'inherited_permissions' => $inheritedPermissions->values()->toArray(),
                'effective_permissions' => $effectivePermissions,
            ];
        });
    }

    /**
     * Get rollup report with optimized aggregation queries.
     */
    public function getRollupReportOptimized(): array
    {
        $cacheKey = "workstream_rollup_{$this->id}";

        return Cache::remember($cacheKey, 300, function () {
            // Get all workstream IDs in hierarchy
            $allWorkstreamIds = $this->getAllDescendantsOptimized()->pluck('id')->toArray();
            $allWorkstreamIds[] = $this->id;

            // Get aggregated data with optimized queries
            $releaseStats = DB::table('releases')
                ->whereIn('workstream_id', $allWorkstreamIds)
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status');

            $taskStats = DB::table('checklist_item_assignments as cia')
                ->join('releases as r', 'cia.release_id', '=', 'r.id')
                ->whereIn('r.workstream_id', $allWorkstreamIds)
                ->select('cia.status', DB::raw('COUNT(*) as count'))
                ->groupBy('cia.status')
                ->pluck('count', 'status');

            $totalTasks = $taskStats->sum();
            $completedTasks = $taskStats->get('completed', 0);
            $completionPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

            // Get child workstream summaries efficiently
            $childSummaries = $this->getChildWorkstreamSummariesOptimized();

            // Get release details efficiently
            $releaseDetails = $this->getReleaseDetailsOptimized($allWorkstreamIds);

            return [
                'workstream_id' => $this->id,
                'workstream_name' => $this->name,
                'summary' => [
                    'total_releases' => $releaseStats->sum(),
                    'releases_by_status' => [
                        'planned' => $releaseStats->get('planned', 0),
                        'in_progress' => $releaseStats->get('in_progress', 0),
                        'completed' => $releaseStats->get('completed', 0),
                    ],
                    'total_tasks' => $totalTasks,
                    'tasks_by_status' => [
                        'pending' => $taskStats->get('pending', 0),
                        'in_progress' => $taskStats->get('in_progress', 0),
                        'completed' => $completedTasks,
                    ],
                    'completion_percentage' => $completionPercentage,
                ],
                'child_workstreams' => $childSummaries,
                'releases' => $releaseDetails,
            ];
        });
    }

    /**
     * Get child workstream summaries with optimized queries.
     */
    protected function getChildWorkstreamSummariesOptimized(): array
    {
        return DB::table('workstreams as w')
            ->leftJoin('releases as r', 'w.id', '=', 'r.workstream_id')
            ->leftJoin('checklist_item_assignments as cia', 'r.id', '=', 'cia.release_id')
            ->where('w.parent_workstream_id', $this->id)
            ->select(
                'w.id as workstream_id',
                'w.name as workstream_name',
                'w.type',
                DB::raw('COUNT(DISTINCT r.id) as releases_count'),
                DB::raw('COUNT(cia.id) as tasks_count'),
                DB::raw('COUNT(CASE WHEN cia.status = "completed" THEN 1 END) as completed_tasks')
            )
            ->groupBy('w.id', 'w.name', 'w.type')
            ->get()
            ->map(function ($child) {
                $completionPercentage = $child->tasks_count > 0
                    ? round(($child->completed_tasks / $child->tasks_count) * 100, 1)
                    : 0;

                return [
                    'workstream_id' => $child->workstream_id,
                    'workstream_name' => $child->workstream_name,
                    'type' => $child->type,
                    'releases_count' => $child->releases_count,
                    'tasks_count' => $child->tasks_count,
                    'completion_percentage' => $completionPercentage,
                ];
            })
            ->toArray();
    }

    /**
     * Get release details with optimized queries.
     */
    protected function getReleaseDetailsOptimized(array $workstreamIds): array
    {
        return DB::table('releases as r')
            ->leftJoin('workstreams as w', 'r.workstream_id', '=', 'w.id')
            ->leftJoin('checklist_item_assignments as cia', 'r.id', '=', 'cia.release_id')
            ->whereIn('r.workstream_id', $workstreamIds)
            ->select(
                'r.id',
                'r.name',
                'r.status',
                'w.name as workstream_name',
                DB::raw('COUNT(cia.id) as tasks_count')
            )
            ->groupBy('r.id', 'r.name', 'r.status', 'w.name')
            ->get()
            ->map(function ($release) {
                return [
                    'id' => $release->id,
                    'name' => $release->name,
                    'status' => $release->status,
                    'workstream_name' => $release->workstream_name,
                    'tasks_count' => $release->tasks_count,
                ];
            })
            ->toArray();
    }

    /**
     * Update hierarchy depth for this workstream and all descendants.
     */
    public function updateHierarchyDepthForSubtree(): void
    {
        DB::statement("
            WITH RECURSIVE workstream_hierarchy AS (
                -- Base case: this workstream
                SELECT id, parent_workstream_id, ? as depth
                FROM workstreams
                WHERE id = ?

                UNION ALL

                -- Recursive case: children at increasing depths
                SELECT w.id, w.parent_workstream_id, wh.depth + 1 as depth
                FROM workstreams w
                INNER JOIN workstream_hierarchy wh ON w.parent_workstream_id = wh.id
            )
            UPDATE workstreams
            SET hierarchy_depth = (
                SELECT depth
                FROM workstream_hierarchy
                WHERE workstream_hierarchy.id = workstreams.id
            )
            WHERE id IN (
                SELECT id FROM workstream_hierarchy
            )
        ", [$this->hierarchy_depth ?? 1, $this->id]);

        // Clear relevant caches
        $this->clearHierarchyCaches();
    }

    /**
     * Clear hierarchy-related caches for this workstream.
     */
    public function clearHierarchyCaches(): void
    {
        $patterns = [
            "workstream_descendants_{$this->id}",
            "workstream_ancestors_{$this->id}",
            "workstream_tree_{$this->id}",
            "workstream_rollup_{$this->id}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }

        // Also clear caches for ancestors since they may be affected
        $ancestors = $this->getAllAncestorsOptimized();
        foreach ($ancestors as $ancestor) {
            Cache::forget("workstream_descendants_{$ancestor->id}");
            Cache::forget("workstream_tree_{$ancestor->id}");
            Cache::forget("workstream_rollup_{$ancestor->id}");
        }
    }
}