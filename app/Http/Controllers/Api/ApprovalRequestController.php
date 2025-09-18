<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\ApprovalResponse;
use App\Models\Release;
use App\Models\User;
use App\Models\Workstream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApprovalRequestController extends Controller
{
    /**
     * Store multiple approval requests for a release.
     */
    public function storeForRelease(Request $request, Release $release): JsonResponse
    {
        $request->validate([
            'approval_requests' => 'required|array|min:1',
            'approval_requests.*.approval_type' => ['required', Rule::in(ApprovalRequest::APPROVAL_TYPES)],
            'approval_requests.*.approver_id' => 'required|exists:users,id',
            'approval_requests.*.description' => 'required|string|max:65535',
            'approval_requests.*.due_date' => 'required|date|after_or_equal:today',
            'approval_requests.*.priority' => ['required', Rule::in(ApprovalRequest::PRIORITIES)],
        ]);

        $approvalRequests = collect();

        foreach ($request->approval_requests as $requestData) {
            $approvalRequest = ApprovalRequest::create(array_merge($requestData, [
                'release_id' => $release->id,
                'status' => 'pending',
            ]));

            $approvalRequest->load(['approver:id,name,email']);
            $approvalRequests->push($approvalRequest);
        }

        return response()->json([
            'data' => $approvalRequests
        ], 201);
    }

    /**
     * Get approval requests for a release with optional status filtering.
     */
    public function indexForRelease(Request $request, Release $release): JsonResponse
    {
        $query = $release->approvalRequests()->with(['approver:id,name,email', 'response']);

        if ($request->has('status')) {
            switch ($request->status) {
                case 'overdue':
                    $query->overdue();
                    break;
                case 'due_soon':
                    $query->dueSoon();
                    break;
                case 'pending':
                    $query->pending();
                    break;
                default:
                    $query->where('status', $request->status);
            }
        }

        $approvalRequests = $query->get()->map(function ($approval) {
            $data = $approval->toArray();
            $data['days_until_due'] = $approval->getDaysUntilDue();
            return $data;
        });

        return response()->json([
            'data' => $approvalRequests
        ]);
    }

    /**
     * Get comprehensive approval status for a release.
     */
    public function statusForRelease(Release $release): JsonResponse
    {
        $approvalRequests = $release->approvalRequests()
            ->with(['approver:id,name,email', 'response'])
            ->get();

        $totalApprovals = $approvalRequests->count();
        $approvedCount = $approvalRequests->where('status', 'approved')->count();
        $rejectedCount = $approvalRequests->where('status', 'rejected')->count();
        $completedCount = $approvedCount + $rejectedCount + $approvalRequests->where('status', 'needs_changes')->count();

        // Determine overall status
        $overallStatus = 'pending';
        if ($totalApprovals > 0) {
            if ($approvedCount === $totalApprovals) {
                $overallStatus = 'approved';
            } elseif ($completedCount > 0) {
                $overallStatus = 'partially_approved';
            } elseif ($rejectedCount > 0 && $completedCount === $totalApprovals) {
                // Only set to rejected if ALL approvals are completed and there are rejections
                $overallStatus = 'rejected';
            }
        }

        $pendingApprovals = $approvalRequests->where('status', 'pending')->map(function ($approval) {
            return [
                'id' => $approval->id,
                'approval_type' => $approval->approval_type,
                'approver' => $approval->approver,
                'due_date' => $approval->due_date,
                'days_until_due' => $approval->getDaysUntilDue(),
            ];
        })->values();

        $blockedApprovals = $approvalRequests->where('status', 'rejected')->map(function ($approval) {
            return [
                'id' => $approval->id,
                'approval_type' => $approval->approval_type,
                'rejection_reason' => $approval->response?->comments,
            ];
        })->values();

        $formattedRequests = $approvalRequests->map(function ($approval) {
            $data = [
                'id' => $approval->id,
                'approval_type' => $approval->approval_type,
                'status' => $approval->status,
                'due_date' => $approval->due_date,
                'priority' => $approval->priority,
                'approver' => $approval->approver,
                'response' => [
                    'decision' => $approval->response?->decision,
                    'comments' => $approval->response?->comments,
                    'conditions' => $approval->response?->conditions,
                    'responded_at' => $approval->response?->responded_at,
                ],
            ];

            return $data;
        });

        return response()->json([
            'data' => [
                'release_id' => $release->id,
                'overall_status' => $overallStatus,
                'total_approvals_required' => $totalApprovals,
                'approvals_completed' => $completedCount,
                'approval_requests' => $formattedRequests,
                'pending_approvals' => $pendingApprovals,
                'blocked_approvals' => $blockedApprovals,
            ]
        ]);
    }

    /**
     * Respond to an approval request.
     */
    public function respond(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $request->validate([
            'decision' => ['required', Rule::in(ApprovalResponse::DECISIONS)],
            'comments' => 'nullable|string|max:65535',
            'conditions' => 'nullable|array',
            'conditions.*' => 'string|max:500',
        ]);

        if (!$approvalRequest->canRespond($request->user())) {
            return response()->json([
                'message' => 'You are not authorized to respond to this approval request.'
            ], 403);
        }

        DB::transaction(function () use ($request, $approvalRequest) {
            $response = ApprovalResponse::create([
                'approval_request_id' => $approvalRequest->id,
                'responder_id' => $request->user()->id,
                'decision' => $request->decision,
                'comments' => $request->comments,
                'conditions' => $request->conditions,
            ]);

            $approvalRequest->update(['status' => $request->decision]);

            return $response;
        });

        $response = $approvalRequest->response()->with('responder:id,name,email')->first();

        return response()->json([
            'data' => $response
        ], 201);
    }

    /**
     * Update an approval request.
     */
    public function update(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $request->validate([
            'description' => 'sometimes|required|string|max:65535',
            'due_date' => 'sometimes|required|date|after_or_equal:today',
            'priority' => ['sometimes', 'required', Rule::in(ApprovalRequest::PRIORITIES)],
            'approver_id' => 'sometimes|required|exists:users,id',
        ]);

        $approvalRequest->update($request->only([
            'description', 'due_date', 'priority', 'approver_id'
        ]));

        $approvalRequest->load('approver:id,name,email');

        return response()->json([
            'data' => $approvalRequest
        ]);
    }

    /**
     * Cancel an approval request.
     */
    public function cancel(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $request->validate([
            'cancellation_reason' => 'required|string|max:65535',
        ]);

        $approvalRequest->cancel($request->cancellation_reason);

        return response()->json([
            'data' => $approvalRequest->fresh()
        ]);
    }

    /**
     * Send reminders for overdue or due soon approvals.
     */
    public function sendReminders(Request $request): JsonResponse
    {
        $request->validate([
            'release_id' => 'nullable|exists:releases,id',
            'reminder_type' => 'required|in:overdue,due_soon',
        ]);

        $query = ApprovalRequest::with('approver:id,name,email');

        if ($request->release_id) {
            $query->where('release_id', $request->release_id);
        }

        if ($request->reminder_type === 'overdue') {
            $query->overdue();
        } else {
            $query->dueSoon();
        }

        $approvals = $query->get();

        $recipients = $approvals->map(function ($approval) {
            $approval->recordReminderSent();

            return [
                'approver_id' => $approval->approver->id,
                'approver_email' => $approval->approver->email,
                'approval_type' => $approval->approval_type,
                'days_overdue' => $approval->getDaysOverdue(),
            ];
        });

        return response()->json([
            'data' => [
                'reminders_sent' => $recipients->count(),
                'recipients' => $recipients,
            ]
        ]);
    }

    /**
     * Process expired approval requests.
     */
    public function processExpirations(): JsonResponse
    {
        $expiredApprovals = ApprovalRequest::where('status', 'pending')
            ->whereRaw('due_date + INTERVAL \'1 DAY\' * auto_expire_days < NOW()')
            ->get();

        $expiredApprovals->each->expire();

        $expiredData = $expiredApprovals->map(function ($approval) {
            return [
                'id' => $approval->id,
                'approval_type' => $approval->approval_type,
                'days_overdue' => $approval->getDaysOverdue(),
            ];
        });

        return response()->json([
            'data' => [
                'expired_requests_count' => $expiredApprovals->count(),
                'expired_requests' => $expiredData,
            ]
        ]);
    }

    /**
     * Get approval workflow summary for a workstream.
     */
    public function workstreamSummary(Workstream $workstream): JsonResponse
    {
        $releaseIds = $workstream->releases()->pluck('id');
        $approvals = ApprovalRequest::whereIn('release_id', $releaseIds)
            ->with('release:id,name')
            ->get();

        $totalApprovals = $approvals->count();
        $pendingApprovals = $approvals->where('status', 'pending')->count();
        $approvedRequests = $approvals->where('status', 'approved')->count();
        $rejectedRequests = $approvals->where('status', 'rejected')->count();
        $overdueApprovals = $approvals->filter(fn($a) => $a->isOverdue())->count();

        // Calculate average approval time
        $completedApprovals = $approvals->whereIn('status', ['approved', 'rejected', 'needs_changes']);
        $avgApprovalTime = $completedApprovals->count() > 0
            ? $completedApprovals->avg(fn($a) => $a->created_at->diffInDays($a->updated_at))
            : 0;

        // Approval types breakdown
        $typeBreakdown = $approvals->groupBy('approval_type')->map(function ($typeApprovals, $type) {
            return [
                'type' => $type,
                'total' => $typeApprovals->count(),
                'pending' => $typeApprovals->where('status', 'pending')->count(),
                'approved' => $typeApprovals->where('status', 'approved')->count(),
                'rejected' => $typeApprovals->where('status', 'rejected')->count(),
            ];
        })->values();

        // Releases needing attention
        $releaseStats = $approvals->groupBy('release_id')->map(function ($releaseApprovals, $releaseId) {
            $release = $releaseApprovals->first()->release;
            $pending = $releaseApprovals->where('status', 'pending')->count();
            $overdue = $releaseApprovals->filter(fn($a) => $a->isOverdue())->count();
            $blocked = $releaseApprovals->where('status', 'rejected')->count();

            return [
                'release_id' => $releaseId,
                'release_name' => $release->name,
                'pending_approvals' => $pending,
                'overdue_approvals' => $overdue,
                'blocked_approvals' => $blocked,
            ];
        })->filter(function ($stats) {
            return $stats['pending_approvals'] > 0 || $stats['overdue_approvals'] > 0 || $stats['blocked_approvals'] > 0;
        })->values();

        return response()->json([
            'data' => [
                'total_approval_requests' => $totalApprovals,
                'pending_approvals' => $pendingApprovals,
                'approved_requests' => $approvedRequests,
                'rejected_requests' => $rejectedRequests,
                'overdue_approvals' => $overdueApprovals,
                'average_approval_time_days' => round($avgApprovalTime, 1),
                'approval_types_breakdown' => $typeBreakdown,
                'releases_needing_attention' => $releaseStats,
            ]
        ]);
    }
}
