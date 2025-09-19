<?php

namespace App\Services;

use App\Models\Communication;
use App\Models\CommunicationParticipant;
use App\Models\Release;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Service class for handling communication-related business logic.
 *
 * This service manages all communication logging, retrieval, and analytics operations
 * for releases. It provides methods for creating communication records, managing
 * participant status, generating analytics, and handling follow-up tracking.
 *
 * @package App\Services
 */
class CommunicationService
{
    /**
     * Store a new communication log entry for a release.
     *
     * Creates a comprehensive communication record with participant tracking,
     * metadata support, and automatic timestamp handling. Supports various
     * communication channels and compliance tagging.
     *
     * @param Release $release The release this communication is associated with
     * @param array $data Communication data including channel, subject, content, etc.
     * @param array $participants Array of participant data for tracking delivery status
     * @return Communication The created communication with loaded relationships
     */
    public function logCommunication(Release $release, array $data, array $participants = []): Communication
    {
        $communicationData = [
            'release_id' => $release->id,
            'initiated_by_user_id' => Auth::id(),
            'channel' => $data['channel'],
            'subject' => $data['subject'],
            'content' => $data['content'],
            'communication_type' => $data['communication_type'],
            'direction' => $data['direction'],
            'priority' => $data['priority'] ?? 'medium',
            'communication_date' => isset($data['communication_date']) ? Carbon::parse($data['communication_date']) : now(),
            'external_id' => $data['external_id'] ?? null,
            'thread_id' => $data['thread_id'] ?? null,
            'is_sensitive' => $data['is_sensitive'] ?? false,
            'compliance_tags' => $data['compliance_tags'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'attachments' => $data['attachments'] ?? null,
        ];

        $communication = Communication::logCommunication($communicationData, $participants);
        $communication->load(['initiatedBy:id,name,email', 'participants.user:id,name,email']);

        return $communication;
    }

    /**
     * Get communication history for a release with filtering options.
     */
    public function getCommunicationsForRelease(Release $release, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $release->communications()
            ->with(['initiatedBy:id,name,email', 'participants.user:id,name,email']);

        // Apply filters
        if (isset($filters['channel'])) {
            $query->byChannel($filters['channel']);
        }

        if (isset($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (isset($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }

        if (isset($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->inDateRange(
                Carbon::parse($filters['start_date']),
                Carbon::parse($filters['end_date'])
            );
        }

        if (isset($filters['thread_id'])) {
            $query->inThread($filters['thread_id']);
        }

        if (isset($filters['sensitive_only']) && $filters['sensitive_only']) {
            $query->sensitive();
        }

        if (isset($filters['participant_id'])) {
            $query->whereHas('participants', function ($participantQuery) use ($filters) {
                $participantQuery->where('user_id', $filters['participant_id']);
            });
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'communication_date';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * Get a communication with full details.
     */
    public function getCommunicationDetails(Communication $communication): array
    {
        $communication->load([
            'initiatedBy:id,name,email',
            'participants.user:id,name,email',
            'release:id,name'
        ]);

        // Add thread communications if part of a thread
        $threadCommunications = $communication->getThreadCommunications();

        return [
            'communication' => $communication,
            'thread_communications' => $threadCommunications->count() > 1 ? $threadCommunications : null,
            'requires_follow_up' => $communication->requiresFollowUp(),
            'is_follow_up_overdue' => $communication->isFollowUpOverdue(),
            'days_until_follow_up' => $communication->getDaysUntilFollowUp(),
        ];
    }

    /**
     * Update communication outcome and follow-up actions.
     */
    public function updateCommunicationOutcome(Communication $communication, array $data): Communication
    {
        $communication->updateOutcome(
            $data['outcome_summary'],
            $data['follow_up_actions'] ?? []
        );

        if (isset($data['follow_up_due_date'])) {
            $communication->setFollowUpDueDate(Carbon::parse($data['follow_up_due_date']));
        }

        if (isset($data['status'])) {
            $communication->update(['status' => $data['status']]);
        }

        return $communication->fresh();
    }

    /**
     * Update participant delivery status.
     */
    public function updateParticipantStatus(Communication $communication, CommunicationParticipant $participant, array $data): CommunicationParticipant
    {
        switch ($data['delivery_status']) {
            case 'delivered':
                $participant->markDelivered();
                break;
            case 'read':
                $participant->markRead();
                break;
            case 'responded':
                $participant->markResponded($data['response_content'] ?? null, $data['response_sentiment'] ?? null);
                break;
            case 'failed':
                $participant->markFailed();
                break;
            case 'bounced':
                $participant->markBounced();
                break;
        }

        return $participant->fresh();
    }

    /**
     * Get communication analytics and summary for a release.
     */
    public function getAnalyticsForRelease(Release $release): array
    {
        $communications = $release->communications();

        $analytics = [
            'total_communications' => $communications->count(),
            'by_channel' => $communications->select('channel', \DB::raw('count(*) as count'))
                ->groupBy('channel')
                ->pluck('count', 'channel'),
            'by_type' => $communications->select('communication_type', \DB::raw('count(*) as count'))
                ->groupBy('communication_type')
                ->pluck('count', 'communication_type'),
            'by_priority' => $communications->select('priority', \DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority'),
            'by_status' => $communications->select('status', \DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status'),
            'requiring_follow_up' => $communications->requiringFollowUp()->count(),
            'overdue_follow_ups' => $communications->overdueFollowUp()->count(),
            'sensitive_communications' => $communications->sensitive()->count(),
        ];

        // Participation analytics
        $participantAnalytics = CommunicationParticipant::whereHas('communication', function ($query) use ($release) {
            $query->where('release_id', $release->id);
        })->select('delivery_status', \DB::raw('count(*) as count'))
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

        return $analytics;
    }

    /**
     * Search communications across releases.
     */
    public function searchCommunications(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $searchQuery = trim($filters['query'] ?? '');

        // Validate search input to prevent performance issues
        if (strlen($searchQuery) < 2) {
            throw new \InvalidArgumentException('Search query must be at least 2 characters long.');
        }

        // Sanitize search query to prevent wildcard abuse
        $searchQuery = str_replace(['%', '_'], ['\%', '\_'], $searchQuery);

        $query = Communication::with(['initiatedBy:id,name,email', 'participants.user:id,name,email', 'release:id,name'])
            ->where(function ($subQuery) use ($searchQuery) {
                $subQuery->where('subject', 'like', '%' . $searchQuery . '%')
                         ->orWhere('content', 'like', '%' . $searchQuery . '%')
                         ->orWhere('outcome_summary', 'like', '%' . $searchQuery . '%');
            });

        // Apply filters
        if (isset($filters['release_id'])) {
            $query->forRelease($filters['release_id']);
        }

        if (isset($filters['channel'])) {
            $query->byChannel($filters['channel']);
        }

        if (isset($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->inDateRange(
                Carbon::parse($filters['start_date']),
                Carbon::parse($filters['end_date'])
            );
        }

        return $query->orderBy('communication_date', 'desc')->paginate($perPage);
    }

    /**
     * Get communications requiring follow-up across all releases.
     */
    public function getFollowUps(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Communication::with(['initiatedBy:id,name,email', 'release:id,name']);

        if (isset($filters['status']) && $filters['status'] === 'overdue') {
            $query->overdueFollowUp();
        } else {
            $query->requiringFollowUp();
        }

        if (isset($filters['release_id'])) {
            $query->forRelease($filters['release_id']);
        }

        $communications = $query->orderBy('follow_up_due_date', 'asc')->paginate($perPage);

        // Add calculated fields
        $communications->getCollection()->transform(function ($communication) {
            $communication->days_until_follow_up = $communication->getDaysUntilFollowUp();
            $communication->is_overdue = $communication->isFollowUpOverdue();
            return $communication;
        });

        return $communications;
    }
}