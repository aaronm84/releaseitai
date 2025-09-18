<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class ChecklistItemDependency extends Model
{
    use HasFactory;

    protected $fillable = [
        'prerequisite_assignment_id',
        'dependent_assignment_id',
        'dependency_type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the prerequisite assignment.
     */
    public function prerequisiteAssignment(): BelongsTo
    {
        return $this->belongsTo(ChecklistItemAssignment::class, 'prerequisite_assignment_id');
    }

    /**
     * Get the dependent assignment.
     */
    public function dependentAssignment(): BelongsTo
    {
        return $this->belongsTo(ChecklistItemAssignment::class, 'dependent_assignment_id');
    }

    /**
     * Check if creating this dependency would create a circular dependency.
     *
     * @param int $prerequisiteId
     * @param int $dependentId
     * @return bool
     */
    public static function wouldCreateCircularDependency(int $prerequisiteId, int $dependentId): bool
    {
        // If prerequisite == dependent, it's obviously circular
        if ($prerequisiteId === $dependentId) {
            return true;
        }

        // Check if the dependent assignment already has the prerequisite in its dependency chain
        return self::hasPathBetween($dependentId, $prerequisiteId);
    }

    /**
     * Check if there's a dependency path from one assignment to another.
     * Uses depth-first search to detect cycles.
     *
     * @param int $fromAssignmentId
     * @param int $toAssignmentId
     * @param array $visited
     * @return bool
     */
    private static function hasPathBetween(int $fromAssignmentId, int $toAssignmentId, array $visited = []): bool
    {
        // Prevent infinite loops
        if (in_array($fromAssignmentId, $visited)) {
            return true; // Found a cycle
        }

        $visited[] = $fromAssignmentId;

        // Get all assignments that depend on the current assignment
        $dependents = self::where('prerequisite_assignment_id', $fromAssignmentId)
            ->where('is_active', true)
            ->pluck('dependent_assignment_id');

        foreach ($dependents as $dependentId) {
            if ($dependentId == $toAssignmentId) {
                return true; // Direct path found
            }

            // Recursively check if there's a path from this dependent to the target
            if (self::hasPathBetween($dependentId, $toAssignmentId, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all dependencies for a given assignment (both as prerequisite and dependent).
     *
     * @param int $assignmentId
     * @return Collection
     */
    public static function getAllDependenciesForAssignment(int $assignmentId): Collection
    {
        return self::where(function ($query) use ($assignmentId) {
            $query->where('prerequisite_assignment_id', $assignmentId)
                  ->orWhere('dependent_assignment_id', $assignmentId);
        })->where('is_active', true)->get();
    }

    /**
     * Get the full dependency chain starting from an assignment.
     *
     * @param int $assignmentId
     * @return array
     */
    public static function getDependencyChain(int $assignmentId): array
    {
        $chain = [];
        $visited = [];

        self::buildDependencyChain($assignmentId, $chain, $visited);

        return $chain;
    }

    /**
     * Recursively build the dependency chain.
     *
     * @param int $assignmentId
     * @param array $chain
     * @param array $visited
     */
    private static function buildDependencyChain(int $assignmentId, array &$chain, array &$visited): void
    {
        if (in_array($assignmentId, $visited)) {
            return; // Prevent infinite loops
        }

        $visited[] = $assignmentId;
        $chain[] = $assignmentId;

        // Get all assignments that this assignment depends on
        $prerequisites = self::where('dependent_assignment_id', $assignmentId)
            ->where('is_active', true)
            ->pluck('prerequisite_assignment_id');

        foreach ($prerequisites as $prerequisiteId) {
            self::buildDependencyChain($prerequisiteId, $chain, $visited);
        }
    }

    /**
     * Check if an assignment can be started based on its dependencies.
     *
     * @param int $assignmentId
     * @return bool
     */
    public static function canAssignmentBeStarted(int $assignmentId): bool
    {
        // Get all blocking prerequisites for this assignment
        $blockingPrerequisites = self::where('dependent_assignment_id', $assignmentId)
            ->where('dependency_type', 'blocks')
            ->where('is_active', true)
            ->with('prerequisiteAssignment')
            ->get();

        // Check if all blocking prerequisites are completed
        foreach ($blockingPrerequisites as $dependency) {
            if ($dependency->prerequisiteAssignment->status !== 'completed') {
                return false;
            }
        }

        return true;
    }

    /**
     * Get assignments that are blocked by a given assignment.
     *
     * @param int $assignmentId
     * @return Collection
     */
    public static function getBlockedAssignments(int $assignmentId): Collection
    {
        return self::where('prerequisite_assignment_id', $assignmentId)
            ->where('dependency_type', 'blocks')
            ->where('is_active', true)
            ->with('dependentAssignment')
            ->get()
            ->pluck('dependentAssignment');
    }

    /**
     * Scope to get only active dependencies.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get dependencies by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('dependency_type', $type);
    }

    /**
     * Scope to get blocking dependencies.
     */
    public function scopeBlocking($query)
    {
        return $query->where('dependency_type', 'blocks');
    }

    /**
     * Scope to get enabling dependencies.
     */
    public function scopeEnabling($query)
    {
        return $query->where('dependency_type', 'enables');
    }

    /**
     * Scope to get informational dependencies.
     */
    public function scopeInformational($query)
    {
        return $query->where('dependency_type', 'informs');
    }

    /**
     * Scope to get dependencies for a specific assignment as prerequisite.
     */
    public function scopeForPrerequisite($query, int $assignmentId)
    {
        return $query->where('prerequisite_assignment_id', $assignmentId);
    }

    /**
     * Scope to get dependencies for a specific assignment as dependent.
     */
    public function scopeForDependent($query, int $assignmentId)
    {
        return $query->where('dependent_assignment_id', $assignmentId);
    }

    /**
     * Scope to get dependencies involving a specific assignment (either as prerequisite or dependent).
     */
    public function scopeInvolving($query, int $assignmentId)
    {
        return $query->where(function ($q) use ($assignmentId) {
            $q->where('prerequisite_assignment_id', $assignmentId)
              ->orWhere('dependent_assignment_id', $assignmentId);
        });
    }
}
