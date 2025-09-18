<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistItemAssignment;
use App\Models\Release;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChecklistAssignmentController extends Controller
{
    /**
     * Store multiple checklist item assignments for a release.
     */
    public function store(Request $request, Release $release): JsonResponse
    {
        $request->validate([
            'assignments' => 'required|array|min:1',
            'assignments.*.checklist_item_id' => 'required|exists:checklist_items,id',
            'assignments.*.assignee_id' => 'required|exists:users,id',
            'assignments.*.due_date' => 'required|date|after:now',
            'assignments.*.priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
        ]);

        $createdAssignments = [];

        DB::transaction(function () use ($request, $release, &$createdAssignments) {
            foreach ($request->assignments as $assignmentData) {
                $assignment = ChecklistItemAssignment::create([
                    'checklist_item_id' => $assignmentData['checklist_item_id'],
                    'assignee_id' => $assignmentData['assignee_id'],
                    'release_id' => $release->id,
                    'due_date' => $assignmentData['due_date'],
                    'priority' => $assignmentData['priority'],
                    'status' => 'pending',
                    'assigned_at' => now(),
                ]);

                $createdAssignments[] = $assignment->load(['assignee', 'checklistItem']);
            }
        });

        return response()->json([
            'data' => collect($createdAssignments)->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'checklist_item_id' => $assignment->checklist_item_id,
                    'assignee_id' => $assignment->assignee_id,
                    'release_id' => $assignment->release_id,
                    'due_date' => $assignment->due_date->toDateString(),
                    'priority' => $assignment->priority,
                    'status' => $assignment->status,
                    'assigned_at' => $assignment->assigned_at->toISOString(),
                    'assignee' => [
                        'id' => $assignment->assignee->id,
                        'name' => $assignment->assignee->name,
                        'email' => $assignment->assignee->email,
                    ],
                    'checklist_item' => [
                        'id' => $assignment->checklistItem->id,
                        'title' => $assignment->checklistItem->title,
                        'description' => $assignment->checklistItem->description,
                        'estimated_hours' => $assignment->checklistItem->estimated_hours,
                        'sla_hours' => $assignment->checklistItem->sla_hours,
                    ],
                ];
            }),
        ], 201);
    }

    /**
     * Get checklist assignments for a release with filtering.
     */
    public function index(Request $request, Release $release): JsonResponse
    {
        $query = ChecklistItemAssignment::with(['assignee', 'checklistItem'])
            ->forRelease($release->id);

        // Apply filters
        if ($request->has('assignee_id')) {
            $query->forAssignee($request->assignee_id);
        }

        if ($request->has('status')) {
            if ($request->status === 'overdue') {
                $query->overdue();
            } elseif ($request->status === 'at_risk') {
                $query->atRisk();
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        $assignments = $query->orderBy('due_date')->get();

        return response()->json([
            'data' => $assignments->map(function ($assignment) {
                return $this->formatAssignmentResponse($assignment);
            }),
        ]);
    }

    /**
     * Show a specific assignment with SLA information.
     */
    public function show(ChecklistItemAssignment $assignment): JsonResponse
    {
        $assignment->load(['assignee', 'checklistItem', 'release']);

        $responseData = $this->formatAssignmentResponse($assignment);

        // Add SLA information
        $responseData['sla_deadline'] = $assignment->sla_deadline?->toISOString();
        $responseData['hours_until_sla_breach'] = $assignment->hours_until_sla_breach;
        $responseData['is_sla_breached'] = $assignment->is_sla_breached;
        $responseData['sla_status'] = $assignment->sla_status;

        return response()->json(['data' => $responseData]);
    }

    /**
     * Reassign an assignment to a different user.
     */
    public function reassign(Request $request, ChecklistItemAssignment $assignment): JsonResponse
    {
        $request->validate([
            'new_assignee_id' => 'required|exists:users,id',
            'reassignment_reason' => 'required|string|max:1000',
        ]);

        $assignment->reassignTo(
            $request->new_assignee_id,
            $request->reassignment_reason
        );

        $assignment->load(['assignee', 'previousAssignee']);

        return response()->json([
            'data' => array_merge($this->formatAssignmentResponse($assignment), [
                'reassigned' => $assignment->reassigned,
                'reassignment_reason' => $assignment->reassignment_reason,
                'previous_assignee' => $assignment->previousAssignee ? [
                    'id' => $assignment->previousAssignee->id,
                    'name' => $assignment->previousAssignee->name,
                    'email' => $assignment->previousAssignee->email,
                ] : null,
            ]),
        ]);
    }

    /**
     * Escalate an assignment.
     */
    public function escalate(Request $request, ChecklistItemAssignment $assignment): JsonResponse
    {
        $request->validate([
            'escalation_reason' => 'required|string|max:1000',
            'notify_stakeholders' => 'boolean',
        ]);

        $assignment->escalate($request->escalation_reason);

        return response()->json([
            'data' => [
                'escalated' => $assignment->escalated,
                'escalated_at' => $assignment->escalated_at->toISOString(),
                'escalation_reason' => $assignment->escalation_reason,
            ],
        ]);
    }

    /**
     * Update assignment status.
     */
    public function updateStatus(Request $request, ChecklistItemAssignment $assignment): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'completed', 'blocked', 'cancelled'])],
            'notes' => 'nullable|string|max:2000',
        ]);

        $updateData = ['status' => $request->status];

        if ($request->has('notes')) {
            $updateData['notes'] = $request->notes;
        }

        // Set timestamps based on status
        if ($request->status === 'in_progress' && $assignment->status !== 'in_progress') {
            $updateData['started_at'] = now();
        } elseif ($request->status === 'completed' && $assignment->status !== 'completed') {
            $updateData['completed_at'] = now();
        }

        $assignment->update($updateData);

        return response()->json([
            'data' => $this->formatAssignmentResponse($assignment),
        ]);
    }

    /**
     * Format assignment response data.
     */
    private function formatAssignmentResponse(ChecklistItemAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'checklist_item_id' => $assignment->checklist_item_id,
            'assignee_id' => $assignment->assignee_id,
            'release_id' => $assignment->release_id,
            'due_date' => $assignment->due_date->toDateString(),
            'priority' => $assignment->priority,
            'status' => $assignment->status,
            'assigned_at' => $assignment->assigned_at->toISOString(),
            'started_at' => $assignment->started_at?->toISOString(),
            'completed_at' => $assignment->completed_at?->toISOString(),
            'notes' => $assignment->notes,
            'escalated' => $assignment->escalated,
            'escalated_at' => $assignment->escalated_at?->toISOString(),
            'escalation_reason' => $assignment->escalation_reason,
            'reassigned' => $assignment->reassigned,
            'reassignment_reason' => $assignment->reassignment_reason,
            'is_overdue' => $assignment->is_overdue,
            'is_at_risk' => $assignment->is_at_risk,
            'assignee' => $assignment->assignee ? [
                'id' => $assignment->assignee->id,
                'name' => $assignment->assignee->name,
                'email' => $assignment->assignee->email,
            ] : null,
            'checklist_item' => $assignment->checklistItem ? [
                'id' => $assignment->checklistItem->id,
                'title' => $assignment->checklistItem->title,
                'description' => $assignment->checklistItem->description,
                'estimated_hours' => $assignment->checklistItem->estimated_hours,
                'sla_hours' => $assignment->checklistItem->sla_hours,
            ] : null,
        ];
    }
}
