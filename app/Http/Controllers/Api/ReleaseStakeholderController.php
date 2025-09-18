<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStakeholderReleaseRequest;
use App\Http\Requests\UpdateStakeholderReleaseRequest;
use App\Models\Release;
use App\Models\StakeholderRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReleaseStakeholderController extends Controller
{
    /**
     * Display a listing of stakeholders for a release.
     */
    public function index(Request $request, Release $release): JsonResponse
    {
        // Check authorization
        if (auth()->id() !== $release->workstream->owner_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $query = $release->stakeholderReleases()->with('user');

        // Filter by role if provided
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $stakeholders = $query->get();

        return response()->json([
            'data' => $stakeholders->map(function ($stakeholderRelease) {
                return [
                    'id' => $stakeholderRelease->id,
                    'user_id' => $stakeholderRelease->user_id,
                    'release_id' => $stakeholderRelease->release_id,
                    'role' => $stakeholderRelease->role,
                    'notification_preference' => $stakeholderRelease->notification_preference,
                    'created_at' => $stakeholderRelease->created_at,
                    'user' => [
                        'id' => $stakeholderRelease->user->id,
                        'name' => $stakeholderRelease->user->name,
                        'email' => $stakeholderRelease->user->email,
                    ],
                ];
            })
        ]);
    }

    /**
     * Store newly created stakeholders for a release.
     */
    public function store(StoreStakeholderReleaseRequest $request, Release $release): JsonResponse
    {
        $stakeholderData = collect($request->validated()['stakeholders']);
        $createdStakeholders = collect();

        foreach ($stakeholderData as $data) {
            $stakeholderRelease = StakeholderRelease::create([
                'user_id' => $data['user_id'],
                'release_id' => $release->id,
                'role' => $data['role'],
                'notification_preference' => $data['notification_preference'],
            ]);

            $stakeholderRelease->load('user');
            $createdStakeholders->push($stakeholderRelease);
        }

        return response()->json([
            'data' => $createdStakeholders->map(function ($stakeholderRelease) {
                return [
                    'id' => $stakeholderRelease->id,
                    'user_id' => $stakeholderRelease->user_id,
                    'release_id' => $stakeholderRelease->release_id,
                    'role' => $stakeholderRelease->role,
                    'notification_preference' => $stakeholderRelease->notification_preference,
                    'created_at' => $stakeholderRelease->created_at,
                    'user' => [
                        'id' => $stakeholderRelease->user->id,
                        'name' => $stakeholderRelease->user->name,
                        'email' => $stakeholderRelease->user->email,
                    ],
                ];
            })
        ], 201);
    }

    /**
     * Update the specified stakeholder relationship.
     */
    public function update(UpdateStakeholderReleaseRequest $request, Release $release, StakeholderRelease $stakeholder): JsonResponse
    {
        // Authorization is handled by the UpdateStakeholderReleaseRequest
        $stakeholder->update($request->validated());
        $stakeholder->load('user');

        return response()->json([
            'data' => [
                'id' => $stakeholder->id,
                'user_id' => $stakeholder->user_id,
                'release_id' => $stakeholder->release_id,
                'role' => $stakeholder->role,
                'notification_preference' => $stakeholder->notification_preference,
                'created_at' => $stakeholder->created_at,
                'updated_at' => $stakeholder->updated_at,
                'user' => [
                    'id' => $stakeholder->user->id,
                    'name' => $stakeholder->user->name,
                    'email' => $stakeholder->user->email,
                ],
            ]
        ]);
    }

    /**
     * Remove the specified stakeholder from the release.
     */
    public function destroy(Release $release, StakeholderRelease $stakeholder): JsonResponse
    {
        // Check authorization
        if (auth()->id() !== $release->workstream->owner_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $stakeholder->delete();

        return response()->json(null, 204);
    }
}
