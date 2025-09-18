<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetFollowUpsRequest;
use App\Http\Requests\IndexCommunicationRequest;
use App\Http\Requests\SearchCommunicationRequest;
use App\Http\Requests\StoreCommunicationRequest;
use App\Http\Requests\UpdateCommunicationOutcomeRequest;
use App\Http\Requests\UpdateParticipantStatusRequest;
use App\Models\Communication;
use App\Models\CommunicationParticipant;
use App\Models\Release;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommunicationController extends Controller
{
    /**
     * Store a new communication log entry for a release.
     */
    public function storeForRelease(StoreCommunicationRequest $request, Release $release): JsonResponse
    {

        $communication = Communication::logCommunication([
            'release_id' => $release->id,
            'initiated_by_user_id' => auth()->id(),
            'channel' => $request->channel,
            'subject' => $request->subject,
            'content' => $request->content,
            'communication_type' => $request->communication_type,
            'direction' => $request->direction,
            'priority' => $request->priority ?? 'medium',
            'communication_date' => $request->communication_date ? Carbon::parse($request->communication_date) : now(),
            'external_id' => $request->external_id,
            'thread_id' => $request->thread_id,
            'is_sensitive' => $request->is_sensitive ?? false,
            'compliance_tags' => $request->compliance_tags,
            'metadata' => $request->metadata,
            'attachments' => $request->attachments,
        ], $request->participants);

        $communication->load(['initiatedBy:id,name,email', 'participants.user:id,name,email']);

        return response()->json([
            'data' => $communication
        ], 201);
    }

    /**
     * Get communication history for a release with filtering options.
     */
    public function indexForRelease(IndexCommunicationRequest $request, Release $release): JsonResponse
    {

        $query = $release->communications()
            ->with(['initiatedBy:id,name,email', 'participants.user:id,name,email']);

        // Apply filters
        if ($request->channel) {
            $query->byChannel($request->channel);
        }

        if ($request->type) {
            $query->byType($request->type);
        }

        if ($request->priority) {
            $query->byPriority($request->priority);
        }

        if ($request->direction) {
            $query->where('direction', $request->direction);
        }

        if ($request->start_date && $request->end_date) {
            $query->inDateRange(
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date)
            );
        }

        if ($request->thread_id) {
            $query->inThread($request->thread_id);
        }

        if ($request->has('sensitive_only') && $request->boolean('sensitive_only')) {
            $query->sensitive();
        }

        if ($request->participant_id) {
            $query->whereHas('participants', function ($participantQuery) use ($request) {
                $participantQuery->where('user_id', $request->participant_id);
            });
        }

        // Apply sorting
        $sortBy = $request->sort_by ?? 'communication_date';
        $sortDirection = $request->sort_direction ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $perPage = $request->per_page ?? 15;
        $communications = $query->paginate($perPage);

        // Transform to include meta field as expected by tests
        $paginatedArray = $communications->toArray();

        return response()->json([
            'data' => $paginatedArray['data'],
            'links' => [
                'first' => $paginatedArray['first_page_url'],
                'last' => $paginatedArray['last_page_url'],
                'prev' => $paginatedArray['prev_page_url'],
                'next' => $paginatedArray['next_page_url'],
            ],
            'meta' => [
                'current_page' => $paginatedArray['current_page'],
                'from' => $paginatedArray['from'],
                'last_page' => $paginatedArray['last_page'],
                'per_page' => $paginatedArray['per_page'],
                'to' => $paginatedArray['to'],
                'total' => $paginatedArray['total'],
                'path' => $paginatedArray['path']
            ]
        ]);
    }

    /**
     * Get a specific communication with full details.
     */
    public function show(Communication $communication): JsonResponse
    {
        $communication->load([
            'initiatedBy:id,name,email',
            'participants.user:id,name,email',
            'release:id,name'
        ]);

        // Add thread communications if part of a thread
        $threadCommunications = $communication->getThreadCommunications();

        return response()->json([
            'data' => [
                'communication' => $communication,
                'thread_communications' => $threadCommunications->count() > 1 ? $threadCommunications : null,
                'requires_follow_up' => $communication->requiresFollowUp(),
                'is_follow_up_overdue' => $communication->isFollowUpOverdue(),
                'days_until_follow_up' => $communication->getDaysUntilFollowUp(),
            ]
        ]);
    }

    /**
     * Update communication outcome and follow-up actions.
     */
    public function updateOutcome(UpdateCommunicationOutcomeRequest $request, Communication $communication): JsonResponse
    {

        $communication->updateOutcome(
            $request->outcome_summary,
            $request->follow_up_actions ?? []
        );

        if ($request->follow_up_due_date) {
            $communication->setFollowUpDueDate(Carbon::parse($request->follow_up_due_date));
        }

        if ($request->status) {
            $communication->update(['status' => $request->status]);
        }

        return response()->json([
            'data' => $communication->fresh()
        ]);
    }

    /**
     * Update participant delivery status.
     */
    public function updateParticipantStatus(UpdateParticipantStatusRequest $request, Communication $communication, CommunicationParticipant $participant): JsonResponse
    {

        switch ($request->delivery_status) {
            case 'delivered':
                $participant->markDelivered();
                break;
            case 'read':
                $participant->markRead();
                break;
            case 'responded':
                $participant->markResponded($request->response_content, $request->response_sentiment);
                break;
            case 'failed':
                $participant->markFailed();
                break;
            case 'bounced':
                $participant->markBounced();
                break;
        }

        return response()->json([
            'data' => $participant->fresh()
        ]);
    }

    /**
     * Get communication analytics and summary for a release.
     */
    public function analyticsForRelease(Release $release): JsonResponse
    {
        $communications = $release->communications();

        $analytics = [
            'total_communications' => $communications->count(),
            'by_channel' => $communications->select('channel', DB::raw('count(*) as count'))
                ->groupBy('channel')
                ->pluck('count', 'channel'),
            'by_type' => $communications->select('communication_type', DB::raw('count(*) as count'))
                ->groupBy('communication_type')
                ->pluck('count', 'communication_type'),
            'by_priority' => $communications->select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority'),
            'by_status' => $communications->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status'),
            'requiring_follow_up' => $communications->requiringFollowUp()->count(),
            'overdue_follow_ups' => $communications->overdueFollowUp()->count(),
            'sensitive_communications' => $communications->sensitive()->count(),
        ];

        // Participation analytics
        $participantAnalytics = CommunicationParticipant::whereHas('communication', function ($query) use ($release) {
            $query->where('release_id', $release->id);
        })->select('delivery_status', DB::raw('count(*) as count'))
        ->groupBy('delivery_status')
        ->pluck('count', 'delivery_status');

        $analytics['participant_engagement'] = $participantAnalytics;

        // Response time analytics
        $avgResponseTime = CommunicationParticipant::whereHas('communication', function ($query) use ($release) {
            $query->where('release_id', $release->id);
        })->whereNotNull('responded_at')
        ->whereNotNull('delivered_at')
        ->get()
        ->avg(function ($participant) {
            return $participant->getResponseTimeHours();
        });

        $analytics['average_response_time_hours'] = round($avgResponseTime, 2);

        return response()->json([
            'data' => $analytics
        ]);
    }

    /**
     * Search communications across releases.
     */
    public function search(SearchCommunicationRequest $request): JsonResponse
    {

        $searchQuery = $request->input('query');
        $query = Communication::with(['initiatedBy:id,name,email', 'participants.user:id,name,email', 'release:id,name'])
            ->where(function ($subQuery) use ($searchQuery) {
                $subQuery->where('subject', 'like', '%' . $searchQuery . '%')
                         ->orWhere('content', 'like', '%' . $searchQuery . '%')
                         ->orWhere('outcome_summary', 'like', '%' . $searchQuery . '%');
            });

        // Apply filters
        if ($request->release_id) {
            $query->forRelease($request->release_id);
        }

        if ($request->channel) {
            $query->byChannel($request->channel);
        }

        if ($request->type) {
            $query->byType($request->type);
        }

        if ($request->start_date && $request->end_date) {
            $query->inDateRange(
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date)
            );
        }

        $perPage = $request->per_page ?? 15;
        $communications = $query->orderBy('communication_date', 'desc')->paginate($perPage);

        // Transform to include meta field as expected by tests
        $paginatedArray = $communications->toArray();

        return response()->json([
            'data' => $paginatedArray['data'],
            'links' => [
                'first' => $paginatedArray['first_page_url'],
                'last' => $paginatedArray['last_page_url'],
                'prev' => $paginatedArray['prev_page_url'],
                'next' => $paginatedArray['next_page_url'],
            ],
            'meta' => [
                'current_page' => $paginatedArray['current_page'],
                'from' => $paginatedArray['from'],
                'last_page' => $paginatedArray['last_page'],
                'per_page' => $paginatedArray['per_page'],
                'to' => $paginatedArray['to'],
                'total' => $paginatedArray['total'],
                'path' => $paginatedArray['path']
            ]
        ]);
    }

    /**
     * Get communications requiring follow-up across all releases.
     */
    public function getFollowUps(GetFollowUpsRequest $request): JsonResponse
    {

        $query = Communication::with(['initiatedBy:id,name,email', 'release:id,name']);

        if ($request->status === 'overdue') {
            $query->overdueFollowUp();
        } else {
            $query->requiringFollowUp();
        }

        if ($request->release_id) {
            $query->forRelease($request->release_id);
        }

        $perPage = $request->per_page ?? 15;
        $communications = $query->orderBy('follow_up_due_date', 'asc')->paginate($perPage);

        // Add calculated fields
        $communications->getCollection()->transform(function ($communication) {
            $communication->days_until_follow_up = $communication->getDaysUntilFollowUp();
            $communication->is_overdue = $communication->isFollowUpOverdue();
            return $communication;
        });

        return response()->json($communications);
    }
}
