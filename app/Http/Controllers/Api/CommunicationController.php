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
use App\Services\CommunicationService;
use App\Services\PaginationService;
use Illuminate\Http\JsonResponse;

class CommunicationController extends Controller
{
    public function __construct(
        private CommunicationService $communicationService,
        private PaginationService $paginationService
    ) {}

    /**
     * Store a new communication log entry for a release.
     */
    public function storeForRelease(StoreCommunicationRequest $request, Release $release): JsonResponse
    {
        $communication = $this->communicationService->logCommunication(
            $release,
            $request->validated(),
            $request->participants ?? []
        );

        return response()->json([
            'data' => $communication
        ], 201);
    }

    /**
     * Get communication history for a release with filtering options.
     */
    public function indexForRelease(IndexCommunicationRequest $request, Release $release): JsonResponse
    {
        $filters = $request->only([
            'channel', 'type', 'priority', 'direction', 'start_date', 'end_date',
            'thread_id', 'sensitive_only', 'participant_id', 'sort_by', 'sort_direction'
        ]);
        $perPage = $request->per_page ?? 15;

        $communications = $this->communicationService->getCommunicationsForRelease(
            $release,
            $filters,
            $perPage
        );

        return $this->paginationService->jsonResponse($communications);
    }

    /**
     * Get a specific communication with full details.
     */
    public function show(Communication $communication): JsonResponse
    {
        $data = $this->communicationService->getCommunicationDetails($communication);

        return response()->json([
            'data' => $data
        ]);
    }

    /**
     * Update communication outcome and follow-up actions.
     */
    public function updateOutcome(UpdateCommunicationOutcomeRequest $request, Communication $communication): JsonResponse
    {
        $updatedCommunication = $this->communicationService->updateCommunicationOutcome(
            $communication,
            $request->validated()
        );

        return response()->json([
            'data' => $updatedCommunication
        ]);
    }

    /**
     * Update participant delivery status.
     */
    public function updateParticipantStatus(UpdateParticipantStatusRequest $request, Communication $communication, CommunicationParticipant $participant): JsonResponse
    {
        $updatedParticipant = $this->communicationService->updateParticipantStatus(
            $communication,
            $participant,
            $request->validated()
        );

        return response()->json([
            'data' => $updatedParticipant
        ]);
    }

    /**
     * Get communication analytics and summary for a release.
     */
    public function analyticsForRelease(Release $release): JsonResponse
    {
        $analytics = $this->communicationService->getAnalyticsForRelease($release);

        return response()->json([
            'data' => $analytics
        ]);
    }

    /**
     * Search communications across releases.
     */
    public function search(SearchCommunicationRequest $request): JsonResponse
    {
        $filters = $request->only(['query', 'release_id', 'channel', 'type', 'start_date', 'end_date']);
        $perPage = $request->per_page ?? 15;

        $communications = $this->communicationService->searchCommunications($filters, $perPage);

        return $this->paginationService->jsonResponse($communications);
    }

    /**
     * Get communications requiring follow-up across all releases.
     */
    public function getFollowUps(GetFollowUpsRequest $request): JsonResponse
    {
        $filters = $request->only(['status', 'release_id']);
        $perPage = $request->per_page ?? 15;

        $communications = $this->communicationService->getFollowUps($filters, $perPage);

        return $this->paginationService->jsonResponse($communications);
    }
}
