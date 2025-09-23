<?php

namespace App\Models;

use App\Models\Traits\WorkstreamHierarchyOptimized;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Workstream extends Model
{
    use HasFactory, WorkstreamHierarchyOptimized;

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
        'hierarchy_depth',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($workstream) {
            if (is_null($workstream->hierarchy_depth)) {
                $workstream->hierarchy_depth = $workstream->calculateHierarchyDepth();
            }
        });

        static::updating(function ($workstream) {
            // If parent_workstream_id changed, recalculate hierarchy_depth
            if ($workstream->isDirty('parent_workstream_id')) {
                $workstream->hierarchy_depth = $workstream->calculateHierarchyDepth();
            }
        });
    }

    /**
     * Calculate hierarchy depth based on parent relationship.
     */
    protected function calculateHierarchyDepth(): int
    {
        if (!$this->parent_workstream_id) {
            return 1;
        }

        $parent = static::find($this->parent_workstream_id);
        return $parent ? $parent->getHierarchyDepth() + 1 : 1;
    }

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
     * Alias for childWorkstreams to match controller expectation.
     */
    public function children(): HasMany
    {
        return $this->childWorkstreams();
    }

    /**
     * Get active releases count.
     */
    public function activeReleases(): HasMany
    {
        return $this->releases()->where('status', '!=', 'completed');
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
        // Use optimized version if available, fallback to cached column or calculation
        return $this->getHierarchyDepthOptimized();
    }

    /**
     * Check if setting the given workstream as parent would create a circular hierarchy.
     */
    public function wouldCreateCircularHierarchy(?int $newParentId): bool
    {
        if (!$newParentId) {
            return false;
        }

        // Use optimized version
        return $this->wouldCreateCircularHierarchyOptimized($newParentId);
    }

    /**
     * Get all ancestors of this workstream (parent, grandparent, etc.).
     */
    public function getAllAncestors(): Collection
    {
        // Use optimized version
        return $this->getAllAncestorsOptimized();
    }

    /**
     * Get all descendants of this workstream (children, grandchildren, etc.).
     */
    public function getAllDescendants(): Collection
    {
        // Use optimized version
        return $this->getAllDescendantsOptimized();
    }

    /**
     * Recursively collect all descendants (legacy method - kept for backwards compatibility).
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
        // Use optimized version
        return $this->buildHierarchyTreeOptimized();
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
        // Use optimized version
        return $this->getEffectivePermissionsForUserOptimized($userId);
    }

    /**
     * Get rollup report including all child workstreams.
     */
    public function getRollupReport(): array
    {
        // Use optimized version
        return $this->getRollupReportOptimized();
    }

    /**
     * Scope a query to only include root workstreams (no parent).
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_workstream_id');
    }

    /**
     * Scope a query to only include children of a specific parent.
     */
    public function scopeChildrenOf($query, $parentId)
    {
        return $query->where('parent_workstream_id', $parentId);
    }

    /**
     * Scope a query to only include workstreams of a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include active workstreams.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope a query to only include workstreams at a specific depth.
     */
    public function scopeAtDepth($query, $depth)
    {
        return $query->where('hierarchy_depth', $depth);
    }

    /**
     * Scope a query to include workstreams with their essential relationships.
     */
    public function scopeWithEssentials($query)
    {
        return $query->with(['owner:id,name,email', 'parentWorkstream:id,name,type']);
    }

    /**
     * Scope a query to include workstreams with full hierarchy relationships.
     */
    public function scopeWithHierarchy($query)
    {
        return $query->with(['owner:id,name,email', 'parentWorkstream:id,name,type', 'childWorkstreams:id,name,type,parent_workstream_id']);
    }

    /**
     * Scope a query to efficiently load workstreams with their complete hierarchy data.
     */
    public function scopeWithCompleteHierarchy($query)
    {
        return $query->with([
            'owner:id,name,email',
            'parentWorkstream:id,name,type,hierarchy_depth',
            'childWorkstreams:id,name,type,parent_workstream_id,hierarchy_depth',
            'childWorkstreams.owner:id,name,email'
        ]);
    }

    /**
     * Scope a query to load workstreams with optimized permission data.
     */
    public function scopeWithPermissions($query, ?int $userId = null)
    {
        if ($userId) {
            return $query->with([
                'permissions' => function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                          ->select('workstream_id', 'user_id', 'permission_type', 'scope');
                }
            ]);
        }

        return $query->with(['permissions:workstream_id,user_id,permission_type,scope']);
    }

    /**
     * Scope a query to bulk load multiple workstreams with essential data.
     */
    public function scopeWithBulkEssentials($query)
    {
        return $query->select([
            'id', 'name', 'type', 'status', 'owner_id',
            'parent_workstream_id', 'hierarchy_depth',
            'created_at', 'updated_at'
        ])->with([
            'owner:id,name,email',
            'parentWorkstream:id,name,type'
        ]);
    }

    /**
     * Scope a query to load workstreams with release summary data.
     */
    public function scopeWithReleaseSummary($query)
    {
        return $query->withCount([
            'releases',
            'releases as active_releases_count' => function ($query) {
                $query->where('status', '!=', 'completed');
            }
        ]);
    }

    /**
     * Scope a query to load workstreams optimized for hierarchy tree building.
     */
    public function scopeForHierarchyTree($query)
    {
        return $query->select([
            'id', 'name', 'type', 'status', 'owner_id',
            'parent_workstream_id', 'hierarchy_depth'
        ])->with(['owner:id,name,email'])
        ->orderBy('hierarchy_depth')
        ->orderBy('name');
    }

    /**
     * Scope a query to get descendants of specific workstreams efficiently.
     */
    public function scopeDescendantsOf($query, array $workstreamIds)
    {
        return $query->whereIn('parent_workstream_id', $workstreamIds)
                     ->orWhereHas('parentWorkstream', function ($subQuery) use ($workstreamIds) {
                         $subQuery->whereIn('parent_workstream_id', $workstreamIds);
                     });
    }

    /**
     * Scope a query to filter by multiple hierarchy depths.
     */
    public function scopeAtDepths($query, array $depths)
    {
        return $query->whereIn('hierarchy_depth', $depths);
    }

    /**
     * Scope a query for bulk operations with minimal data.
     */
    public function scopeForBulkOperations($query)
    {
        return $query->select(['id', 'name', 'type', 'status', 'parent_workstream_id', 'hierarchy_depth']);
    }

    /**
     * Bulk load workstreams with their children using optimized queries.
     */
    public static function loadWithChildrenBulk(array $workstreamIds): Collection
    {
        return static::whereIn('id', $workstreamIds)
            ->withCompleteHierarchy()
            ->get()
            ->load(['childWorkstreams.childWorkstreams']); // Load grandchildren too
    }

    /**
     * Bulk load workstreams with their owners using single query.
     */
    public static function loadWithOwnersBulk(array $workstreamIds): Collection
    {
        return static::whereIn('id', $workstreamIds)
            ->with(['owner:id,name,email'])
            ->get();
    }

    /**
     * Bulk load workstreams with permission context for a user.
     */
    public static function loadWithPermissionsBulk(array $workstreamIds, int $userId): Collection
    {
        return static::whereIn('id', $workstreamIds)
            ->withPermissions($userId)
            ->withBulkEssentials()
            ->get();
    }

    /**
     * Load complete hierarchy trees for multiple root workstreams efficiently.
     */
    public static function loadHierarchyTreesBulk(array $rootWorkstreamIds): Collection
    {
        // First load all root workstreams
        $roots = static::whereIn('id', $rootWorkstreamIds)
            ->forHierarchyTree()
            ->get();

        // Then bulk load all descendants
        $allDescendantIds = [];
        foreach ($roots as $root) {
            $descendants = $root->getAllDescendantsOptimized();
            $allDescendantIds = array_merge($allDescendantIds, $descendants->pluck('id')->toArray());
        }

        if (!empty($allDescendantIds)) {
            // Load all descendants with their relationships in one query
            $descendants = static::whereIn('id', $allDescendantIds)
                ->forHierarchyTree()
                ->get()
                ->keyBy('id');

            // Attach descendants to their parents efficiently
            foreach ($roots as $root) {
                $root->setRelation('allDescendants',
                    $descendants->filter(function ($descendant) use ($root) {
                        return $descendant->hierarchy_depth > $root->hierarchy_depth;
                    })
                );
            }
        }

        return $roots;
    }

    /**
     * Search workstreams with hierarchy context using optimized queries.
     */
    public static function searchWithHierarchyContext(string $searchTerm, array $filters = []): Collection
    {
        $query = static::where('name', 'LIKE', "%{$searchTerm}%")
            ->withCompleteHierarchy();

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['hierarchy_depth'])) {
            $query->where('hierarchy_depth', $filters['hierarchy_depth']);
        }

        return $query->orderBy('hierarchy_depth')
                     ->orderBy('name')
                     ->get();
    }

    /**
     * Get workstreams that need permission validation in bulk.
     */
    public static function loadForPermissionValidation(array $workstreamIds, int $userId): Collection
    {
        return static::whereIn('id', $workstreamIds)
            ->select(['id', 'name', 'type', 'owner_id', 'parent_workstream_id', 'hierarchy_depth'])
            ->with([
                'permissions' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                },
                'parentWorkstream:id,owner_id,parent_workstream_id',
                'parentWorkstream.permissions' => function ($query) use ($userId) {
                    $query->where('user_id', $userId)->where('scope', 'workstream_and_children');
                }
            ])
            ->get();
    }

    /**
     * Create a hierarchy efficiently for testing purposes.
     */
    public static function createHierarchyFast(int $depth = 4, int $branchingFactor = 5, ?User $owner = null): array
    {
        if (!$owner) {
            $owner = User::first() ?? User::factory()->create();
        }

        $allWorkstreams = [];

        // Create root workstreams using the factory for better compatibility
        $roots = collect();
        for ($i = 0; $i < $branchingFactor; $i++) {
            $root = static::factory()->create([
                'name' => "Root Workstream {$i}",
                'type' => 'product_line',
                'status' => 'active',
                'owner_id' => $owner->id,
                'parent_workstream_id' => null,
                'hierarchy_depth' => 1,
            ]);
            $roots->push($root);
        }

        $allWorkstreams = array_merge($allWorkstreams, $roots->toArray());
        $currentLevel = $roots;

        // Create subsequent levels efficiently
        for ($level = 1; $level < $depth; $level++) {
            $nextLevel = collect();

            foreach ($currentLevel as $parent) {
                for ($i = 0; $i < $branchingFactor; $i++) {
                    $child = static::factory()->create([
                        'name' => "Child {$level}-{$i} of {$parent->id}",
                        'type' => $level === $depth - 1 ? 'experiment' : 'initiative',
                        'status' => 'active',
                        'owner_id' => $owner->id,
                        'parent_workstream_id' => $parent->id,
                        'hierarchy_depth' => $level + 1,
                    ]);
                    $nextLevel->push($child);
                }
            }

            $allWorkstreams = array_merge($allWorkstreams, $nextLevel->toArray());
            $currentLevel = $nextLevel;
        }

        return [
            'roots' => $roots,
            'all' => $allWorkstreams,
            'total_count' => count($allWorkstreams)
        ];
    }

    /**
     * Boot method to handle model events for cache invalidation.
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($workstream) {
            // Skip expensive operations during testing
            if (app()->environment('testing')) {
                return;
            }

            $workstream->clearHierarchyCaches();

            // If parent changed, update hierarchy depth for subtree
            if ($workstream->wasChanged('parent_workstream_id')) {
                $workstream->updateHierarchyDepthForSubtree();
            }
        });

        static::deleted(function ($workstream) {
            // Skip expensive operations during testing
            if (app()->environment('testing')) {
                return;
            }

            $workstream->clearHierarchyCaches();
        });
    }
}
