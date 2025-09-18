<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistItemAssignment;
use App\Models\ChecklistItemDependency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChecklistDependencyController extends Controller
{
    /**
     * Store a new dependency between checklist item assignments.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'prerequisite_assignment_id' => 'required|exists:checklist_item_assignments,id',
            'dependent_assignment_id' => 'required|exists:checklist_item_assignments,id|different:prerequisite_assignment_id',
            'dependency_type' => ['required', Rule::in(['blocks', 'enables', 'informs'])],
            'description' => 'nullable|string|max:1000',
        ]);

        // Check for circular dependencies
        if (ChecklistItemDependency::wouldCreateCircularDependency(
            $request->prerequisite_assignment_id,
            $request->dependent_assignment_id
        )) {
            throw ValidationException::withMessages([
                'dependent_assignment_id' => ['Creating this dependency would result in a circular dependency chain.']
            ]);
        }

        $dependency = ChecklistItemDependency::create([
            'prerequisite_assignment_id' => $request->prerequisite_assignment_id,
            'dependent_assignment_id' => $request->dependent_assignment_id,
            'dependency_type' => $request->dependency_type,
            'description' => $request->description,
            'is_active' => true,
        ]);

        $dependency->load(['prerequisiteAssignment.assignee', 'dependentAssignment.assignee']);

        return response()->json([
            'data' => $this->formatDependencyResponse($dependency),
        ], 201);
    }

    /**
     * Show a specific dependency.
     */
    public function show(ChecklistItemDependency $dependency): JsonResponse
    {
        $dependency->load([
            'prerequisiteAssignment.assignee',
            'prerequisiteAssignment.checklistItem',
            'dependentAssignment.assignee',
            'dependentAssignment.checklistItem'
        ]);

        return response()->json([
            'data' => $this->formatDependencyResponse($dependency, true),
        ]);
    }

    /**
     * Update a dependency.
     */
    public function update(Request $request, ChecklistItemDependency $dependency): JsonResponse
    {
        $request->validate([
            'dependency_type' => ['sometimes', Rule::in(['blocks', 'enables', 'informs'])],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ]);

        $dependency->update($request->only(['dependency_type', 'description', 'is_active']));

        $dependency->load(['prerequisiteAssignment.assignee', 'dependentAssignment.assignee']);

        return response()->json([
            'data' => $this->formatDependencyResponse($dependency),
        ]);
    }

    /**
     * Delete a dependency.
     */
    public function destroy(ChecklistItemDependency $dependency): JsonResponse
    {
        $dependency->delete();

        return response()->json(['message' => 'Dependency deleted successfully.']);
    }

    /**
     * Get all dependencies for a specific assignment.
     */
    public function getForAssignment(ChecklistItemAssignment $assignment): JsonResponse
    {
        $dependencies = ChecklistItemDependency::involving($assignment->id)
            ->active()
            ->with([
                'prerequisiteAssignment.assignee',
                'prerequisiteAssignment.checklistItem',
                'dependentAssignment.assignee',
                'dependentAssignment.checklistItem'
            ])
            ->get();

        return response()->json([
            'data' => [
                'assignment_id' => $assignment->id,
                'dependencies' => $dependencies->map(function ($dependency) {
                    return $this->formatDependencyResponse($dependency, true);
                }),
                'can_be_started' => ChecklistItemDependency::canAssignmentBeStarted($assignment->id),
                'blocked_assignments' => ChecklistItemDependency::getBlockedAssignments($assignment->id)
                    ->map(function ($blockedAssignment) {
                        return [
                            'id' => $blockedAssignment->id,
                            'checklist_item' => [
                                'id' => $blockedAssignment->checklistItem->id,
                                'title' => $blockedAssignment->checklistItem->title,
                            ],
                            'assignee' => [
                                'id' => $blockedAssignment->assignee->id,
                                'name' => $blockedAssignment->assignee->name,
                                'email' => $blockedAssignment->assignee->email,
                            ],
                            'status' => $blockedAssignment->status,
                            'due_date' => $blockedAssignment->due_date->toDateString(),
                        ];
                    }),
            ],
        ]);
    }

    /**
     * Format dependency response data.
     */
    private function formatDependencyResponse(ChecklistItemDependency $dependency, bool $includeDetails = false): array
    {
        $response = [
            'id' => $dependency->id,
            'prerequisite_assignment_id' => $dependency->prerequisite_assignment_id,
            'dependent_assignment_id' => $dependency->dependent_assignment_id,
            'dependency_type' => $dependency->dependency_type,
            'description' => $dependency->description,
            'is_active' => $dependency->is_active,
            'created_at' => $dependency->created_at->toISOString(),
            'updated_at' => $dependency->updated_at->toISOString(),
        ];

        if ($includeDetails) {
            $response['prerequisite_assignment'] = [
                'id' => $dependency->prerequisiteAssignment->id,
                'status' => $dependency->prerequisiteAssignment->status,
                'due_date' => $dependency->prerequisiteAssignment->due_date->toDateString(),
                'priority' => $dependency->prerequisiteAssignment->priority,
                'assignee' => $dependency->prerequisiteAssignment->assignee ? [
                    'id' => $dependency->prerequisiteAssignment->assignee->id,
                    'name' => $dependency->prerequisiteAssignment->assignee->name,
                    'email' => $dependency->prerequisiteAssignment->assignee->email,
                ] : null,
                'checklist_item' => $dependency->prerequisiteAssignment->checklistItem ? [
                    'id' => $dependency->prerequisiteAssignment->checklistItem->id,
                    'title' => $dependency->prerequisiteAssignment->checklistItem->title,
                ] : null,
            ];

            $response['dependent_assignment'] = [
                'id' => $dependency->dependentAssignment->id,
                'status' => $dependency->dependentAssignment->status,
                'due_date' => $dependency->dependentAssignment->due_date->toDateString(),
                'priority' => $dependency->dependentAssignment->priority,
                'assignee' => $dependency->dependentAssignment->assignee ? [
                    'id' => $dependency->dependentAssignment->assignee->id,
                    'name' => $dependency->dependentAssignment->assignee->name,
                    'email' => $dependency->dependentAssignment->assignee->email,
                ] : null,
                'checklist_item' => $dependency->dependentAssignment->checklistItem ? [
                    'id' => $dependency->dependentAssignment->checklistItem->id,
                    'title' => $dependency->dependentAssignment->checklistItem->title,
                ] : null,
            ];
        }

        return $response;
    }
}
