<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workstream extends Model
{
    use HasFactory;

    /**
     * Workstream type constants
     */
    const TYPE_PRODUCT_LINE = 'product_line';
    const TYPE_INITIATIVE = 'initiative';
    const TYPE_EXPERIMENT = 'experiment';

    /**
     * Status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_ON_HOLD = 'on_hold';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Maximum hierarchy depth allowed
     */
    const MAX_HIERARCHY_DEPTH = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'type',
        'parent_workstream_id',
        'status',
        'owner_id',
    ];

    /**
     * Get the owner of the workstream.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the parent workstream.
     */
    public function parentWorkstream(): BelongsTo
    {
        return $this->belongsTo(Workstream::class, 'parent_workstream_id');
    }

    /**
     * Get the child workstreams.
     */
    public function childWorkstreams(): HasMany
    {
        return $this->hasMany(Workstream::class, 'parent_workstream_id');
    }

    /**
     * Get the releases for the workstream.
     */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    /**
     * Get the permissions for this workstream.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(WorkstreamPermission::class);
    }

    /**
     * Calculate the hierarchy depth of this workstream.
     * Root workstreams have depth 1.
     */
    public function getHierarchyDepth(): int
    {
        $depth = 1;
        $current = $this;

        while ($current->parent_workstream_id) {
            $depth++;
            $current = $current->parentWorkstream;
        }

        return $depth;
    }

    /**
     * Check if setting the given workstream as parent would create a circular hierarchy.
     */
    public function wouldCreateCircularHierarchy(?int $newParentId): bool
    {
        if (!$newParentId) {
            return false;
        }

        // Can't be parent of itself
        if ($newParentId === $this->id) {
            return true;
        }

        // Check if the new parent is a descendant of this workstream
        $descendants = $this->getAllDescendants();
        return $descendants->contains('id', $newParentId);
    }

    /**
     * Get all ancestors of this workstream (parent, grandparent, etc.).
     */
    public function getAllAncestors(): Collection
    {
        $ancestors = Collection::make();
        $current = $this->parentWorkstream;

        while ($current) {
            $ancestors->push($current);
            $current = $current->parentWorkstream;
        }

        return $ancestors;
    }

    /**
     * Get all descendants of this workstream (children, grandchildren, etc.).
     */
    public function getAllDescendants(): Collection
    {
        $descendants = Collection::make();
        $this->collectDescendants($descendants);
        return $descendants;
    }

    /**
     * Recursively collect all descendants.
     */
    private function collectDescendants(Collection $descendants): void
    {
        foreach ($this->childWorkstreams as $child) {
            $descendants->push($child);
            $child->collectDescendants($descendants);
        }
    }

    /**
     * Check if a user has inherited permissions on this workstream.
     */
    public function userHasInheritedPermission(int $userId, string $permissionType): bool
    {
        $ancestors = $this->getAllAncestors();

        foreach ($ancestors as $ancestor) {
            $permission = $ancestor->permissions()
                ->where('user_id', $userId)
                ->where('permission_type', $permissionType)
                ->where(function ($query) {
                    $query->where('scope', 'workstream_and_children')
                          ->orWhere('scope', 'workstream_only');
                })
                ->first();

            if ($permission && $permission->scope === 'workstream_and_children') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the root workstream (top-level parent).
     */
    public function getRootWorkstream(): Workstream
    {
        $root = $this;

        while ($root->parent_workstream_id) {
            $root = $root->parentWorkstream;
        }

        return $root;
    }

    /**
     * Build a complete hierarchy tree starting from this workstream.
     */
    public function buildHierarchyTree(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'owner' => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ] : null,
            'children' => $this->childWorkstreams->map(function ($child) {
                return $child->buildHierarchyTree();
            })->toArray(),
        ];
    }

    /**
     * Check if this workstream can be deleted (has no children).
     */
    public function canBeDeleted(): bool
    {
        return $this->childWorkstreams()->count() === 0;
    }

    /**
     * Get effective permissions for a user including inherited permissions.
     */
    public function getEffectivePermissionsForUser(int $userId): array
    {
        $directPermissions = $this->permissions()
            ->where('user_id', $userId)
            ->get()
            ->keyBy('permission_type');

        $inheritedPermissions = collect();
        $ancestors = $this->getAllAncestors();

        foreach ($ancestors as $ancestor) {
            $ancestorPermissions = $ancestor->permissions()
                ->where('user_id', $userId)
                ->where('scope', 'workstream_and_children')
                ->get();

            foreach ($ancestorPermissions as $permission) {
                if (!$inheritedPermissions->has($permission->permission_type)) {
                    $inheritedPermissions->put($permission->permission_type, [
                        'permission_type' => $permission->permission_type,
                        'inherited_from_workstream_id' => $ancestor->id,
                        'inherited_from_workstream_name' => $ancestor->name,
                    ]);
                }
            }
        }

        // Determine effective permissions (direct overrides inherited)
        $effectivePermissions = [];
        $permissionHierarchy = ['view' => 1, 'edit' => 2, 'admin' => 3];

        foreach (['view', 'edit', 'admin'] as $permType) {
            $direct = $directPermissions->get($permType);
            $inherited = $inheritedPermissions->get($permType);

            if ($direct) {
                $effectivePermissions[] = $permType;
            } elseif ($inherited) {
                $effectivePermissions[] = $permType;
            }
        }

        // Apply permission hierarchy - if you have admin, you also have edit and view
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
    }

    /**
     * Get rollup report including all child workstreams.
     */
    public function getRollupReport(): array
    {
        $allWorkstreams = collect([$this])->merge($this->getAllDescendants());

        $allReleases = collect();
        $allTasks = collect();

        foreach ($allWorkstreams as $workstream) {
            $releases = $workstream->releases()->with('checklistItemAssignments')->get();
            $allReleases = $allReleases->merge($releases);

            foreach ($releases as $release) {
                $tasks = $release->checklistItemAssignments ?? collect();
                $allTasks = $allTasks->merge($tasks);
            }
        }

        $releasesByStatus = $allReleases->groupBy('status')->map->count();
        $tasksByStatus = $allTasks->groupBy('status')->map->count();

        $totalTasks = $allTasks->count();
        $completedTasks = $tasksByStatus->get('completed', 0);
        $completionPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        return [
            'workstream_id' => $this->id,
            'workstream_name' => $this->name,
            'summary' => [
                'total_releases' => $allReleases->count(),
                'releases_by_status' => [
                    'planned' => $releasesByStatus->get('planned', 0),
                    'in_progress' => $releasesByStatus->get('in_progress', 0),
                    'completed' => $releasesByStatus->get('completed', 0),
                ],
                'total_tasks' => $totalTasks,
                'tasks_by_status' => [
                    'pending' => $tasksByStatus->get('pending', 0),
                    'in_progress' => $tasksByStatus->get('in_progress', 0),
                    'completed' => $completedTasks,
                ],
                'completion_percentage' => $completionPercentage,
            ],
            'child_workstreams' => $this->childWorkstreams->map(function ($child) {
                $childReleases = $child->releases()->with('checklistItemAssignments')->get();
                $childTasks = $childReleases->flatMap->checklistItemAssignments;
                $childCompletedTasks = $childTasks->where('status', 'completed')->count();
                $childTotalTasks = $childTasks->count();
                $childCompletionPercentage = $childTotalTasks > 0 ? round(($childCompletedTasks / $childTotalTasks) * 100, 1) : 0;

                return [
                    'workstream_id' => $child->id,
                    'workstream_name' => $child->name,
                    'type' => $child->type,
                    'releases_count' => $childReleases->count(),
                    'tasks_count' => $childTotalTasks,
                    'completion_percentage' => $childCompletionPercentage,
                ];
            })->toArray(),
            'releases' => $allReleases->map(function ($release) {
                $tasks = $release->checklistItemAssignments ?? collect();
                return [
                    'id' => $release->id,
                    'name' => $release->name,
                    'status' => $release->status,
                    'workstream_name' => $release->workstream->name,
                    'tasks_count' => $tasks->count(),
                ];
            })->toArray(),
        ];
    }
}
