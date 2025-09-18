<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StakeholderReleaseController extends Controller
{
    /**
     * Display a listing of releases for a stakeholder.
     */
    public function index(Request $request, User $stakeholder): JsonResponse
    {
        $query = $stakeholder->stakeholderReleases()->with(['workstream']);

        // Filter by role if provided
        if ($request->has('role')) {
            $query->wherePivot('role', $request->role);
        }

        $releases = $query->get();

        return response()->json([
            'data' => $releases->map(function ($release) {
                return [
                    'id' => $release->id,
                    'name' => $release->name,
                    'description' => $release->description,
                    'workstream_id' => $release->workstream_id,
                    'target_date' => $release->target_date,
                    'status' => $release->status,
                    'created_at' => $release->created_at,
                    'updated_at' => $release->updated_at,
                    'workstream' => [
                        'id' => $release->workstream->id,
                        'name' => $release->workstream->name,
                    ],
                    'stakeholder_role' => $release->pivot->role,
                    'notification_preference' => $release->pivot->notification_preference,
                ];
            })
        ]);
    }
}
