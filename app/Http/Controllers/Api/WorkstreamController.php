<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdateWorkstreamRequest;
use App\Http\Requests\IndexWorkstreamRequest;
use App\Http\Requests\MoveWorkstreamRequest;
use App\Http\Requests\StoreWorkstreamPermissionRequest;
use App\Http\Requests\StoreWorkstreamRequest;
use App\Http\Requests\UpdateWorkstreamRequest;
use App\Models\Workstream;
use App\Models\WorkstreamPermission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkstreamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexWorkstreamRequest $request): JsonResponse
    {
        $query = Workstream::with(['owner', 'parentWorkstream']);

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by parent if provided
        if ($request->has('parent_workstream_id')) {
            if ($request->parent_workstream_id === 'null') {
                $query->whereNull('parent_workstream_id');
            } else {
                $query->where('parent_workstream_id', $request->parent_workstream_id);
            }
        }

        $workstreams = $query->orderBy('name')->get();

        return response()->json([
            'data' => $workstreams->map(function ($workstream) {
                return [
                    'id' => $workstream->id,
                    'name' => $workstream->name,
                    'description' => $workstream->description,
                    'type' => $workstream->type,
                    'status' => $workstream->status,
                    'parent_workstream_id' => $workstream->parent_workstream_id,
                    'owner_id' => $workstream->owner_id,
                    'owner' => $workstream->owner ? [
                        'id' => $workstream->owner->id,
                        'name' => $workstream->owner->name,
                        'email' => $workstream->owner->email,
                    ] : null,
                    'parent_workstream' => $workstream->parentWorkstream ? [
                        'id' => $workstream->parentWorkstream->id,
                        'name' => $workstream->parentWorkstream->name,
                        'type' => $workstream->parentWorkstream->type,
                    ] : null,
                    'created_at' => $workstream->created_at,
                    'updated_at' => $workstream->updated_at,
                ];
            })
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWorkstreamRequest $request): JsonResponse
    {
        $workstream = Workstream::create($request->validated());

        // Load relationships for response
        $workstream->load(['owner', 'parentWorkstream']);

        return response()->json([
            'data' => [
                'id' => $workstream->id,
                'name' => $workstream->name,
                'description' => $workstream->description,
                'type' => $workstream->type,
                'status' => $workstream->status,
                'parent_workstream_id' => $workstream->parent_workstream_id,
                'owner_id' => $workstream->owner_id,
                'owner' => $workstream->owner ? [
                    'id' => $workstream->owner->id,
                    'name' => $workstream->owner->name,
                    'email' => $workstream->owner->email,
                ] : null,
                'parent_workstream' => $workstream->parentWorkstream ? [
                    'id' => $workstream->parentWorkstream->id,
                    'name' => $workstream->parentWorkstream->name,
                    'type' => $workstream->parentWorkstream->type,
                ] : null,
                'created_at' => $workstream->created_at,
                'updated_at' => $workstream->updated_at,
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Workstream $workstream): JsonResponse
    {
        // Check access permissions
        if (!$this->userCanAccessWorkstream($workstream, 'view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $workstream->load(['owner', 'parentWorkstream']);

        return response()->json([
            'data' => [
                'id' => $workstream->id,
                'name' => $workstream->name,
                'description' => $workstream->description,
                'type' => $workstream->type,
                'status' => $workstream->status,
                'parent_workstream_id' => $workstream->parent_workstream_id,
                'owner_id' => $workstream->owner_id,
                'owner' => $workstream->owner ? [
                    'id' => $workstream->owner->id,
                    'name' => $workstream->owner->name,
                    'email' => $workstream->owner->email,
                ] : null,
                'parent_workstream' => $workstream->parentWorkstream ? [
                    'id' => $workstream->parentWorkstream->id,
                    'name' => $workstream->parentWorkstream->name,
                    'type' => $workstream->parentWorkstream->type,
                ] : null,
                'created_at' => $workstream->created_at,
                'updated_at' => $workstream->updated_at,
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWorkstreamRequest $request, Workstream $workstream): JsonResponse
    {
        // Check edit permissions
        if (!$this->userCanAccessWorkstream($workstream, 'edit')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $workstream->update($request->validated());

        // Load relationships for response
        $workstream->load(['owner', 'parentWorkstream']);

        return response()->json([
            'data' => [
                'id' => $workstream->id,
                'name' => $workstream->name,
                'description' => $workstream->description,
                'type' => $workstream->type,
                'status' => $workstream->status,
                'parent_workstream_id' => $workstream->parent_workstream_id,
                'owner_id' => $workstream->owner_id,
                'owner' => $workstream->owner ? [
                    'id' => $workstream->owner->id,
                    'name' => $workstream->owner->name,
                    'email' => $workstream->owner->email,
                ] : null,
                'parent_workstream' => $workstream->parentWorkstream ? [
                    'id' => $workstream->parentWorkstream->id,
                    'name' => $workstream->parentWorkstream->name,
                    'type' => $workstream->parentWorkstream->type,
                ] : null,
                'created_at' => $workstream->created_at,
                'updated_at' => $workstream->updated_at,
            ]
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Workstream $workstream): JsonResponse
    {
        if (!$workstream->canBeDeleted()) {
            return response()->json([
                'message' => 'Cannot delete workstream with child workstreams. Move or delete children first.'
            ], 422);
        }

        $workstream->delete();

        return response()->json(null, 204);
    }

    /**
     * Get the hierarchy tree for a workstream.
     */
    public function hierarchy(Workstream $workstream): JsonResponse
    {
        // Check access permissions
        if (!$this->userCanAccessWorkstream($workstream, 'view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $workstream->load(['owner', 'childWorkstreams.owner', 'childWorkstreams.childWorkstreams.owner']);

        return response()->json([
            'data' => $workstream->buildHierarchyTree()
        ]);
    }

    /**
     * Get rollup report for a workstream.
     */
    public function rollupReport(Workstream $workstream): JsonResponse
    {
        // Check access permissions
        if (!$this->userCanAccessWorkstream($workstream, 'view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => $workstream->getRollupReport()
        ]);
    }

    /**
     * Get permissions for a workstream.
     */
    public function permissions(Workstream $workstream): JsonResponse
    {
        // Check access permissions
        if (!$this->userCanAccessWorkstream($workstream, 'view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $userId = Auth::id();
        $permissions = $workstream->getEffectivePermissionsForUser($userId);

        return response()->json([
            'data' => [
                'user_permissions' => $permissions
            ]
        ]);
    }

    /**
     * Grant permissions on a workstream.
     */
    public function storePermissions(StoreWorkstreamPermissionRequest $request, Workstream $workstream): JsonResponse
    {

        // Check if current user can grant permissions
        // They can grant if they have admin access OR if they own a parent workstream
        $canGrant = $this->userCanAccessWorkstream($workstream, 'admin') ||
                   $this->userOwnsParentWorkstream($workstream);

        if (!$canGrant) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $permission = WorkstreamPermission::updateOrCreate(
            [
                'workstream_id' => $workstream->id,
                'user_id' => $request->user_id,
                'permission_type' => $request->permission_type,
            ],
            [
                'scope' => $request->scope ?? 'workstream_only',
                'granted_by' => Auth::id(),
            ]
        );

        return response()->json([
            'data' => [
                'id' => $permission->id,
                'workstream_id' => $permission->workstream_id,
                'user_id' => $permission->user_id,
                'permission_type' => $permission->permission_type,
                'scope' => $permission->scope,
                'granted_by' => $permission->granted_by,
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at,
            ]
        ], 201);
    }

    /**
     * Move a workstream to a new parent.
     */
    public function move(MoveWorkstreamRequest $request, Workstream $workstream): JsonResponse
    {

        $newParentId = $request->new_parent_workstream_id;

        // Validate hierarchy constraints
        if ($newParentId) {
            // Check for circular hierarchy
            if ($workstream->wouldCreateCircularHierarchy($newParentId)) {
                return response()->json([
                    'errors' => [
                        'new_parent_workstream_id' => ['Cannot create circular workstream relationship.']
                    ]
                ], 422);
            }

            // Check depth limit
            $newParent = Workstream::find($newParentId);
            if ($newParent->getHierarchyDepth() >= Workstream::MAX_HIERARCHY_DEPTH) {
                return response()->json([
                    'errors' => [
                        'new_parent_workstream_id' => ['Workstream hierarchy cannot exceed 3 levels deep.']
                    ]
                ], 422);
            }
        }

        $workstream->update(['parent_workstream_id' => $newParentId]);
        $workstream->load(['parentWorkstream']);

        return response()->json([
            'data' => [
                'id' => $workstream->id,
                'parent_workstream_id' => $workstream->parent_workstream_id,
                'parent_workstream' => $workstream->parentWorkstream ? [
                    'id' => $workstream->parentWorkstream->id,
                    'name' => $workstream->parentWorkstream->name,
                    'type' => $workstream->parentWorkstream->type,
                ] : null,
            ]
        ]);
    }

    /**
     * Bulk update workstreams.
     */
    public function bulkUpdate(BulkUpdateWorkstreamRequest $request): JsonResponse
    {

        $workstreamIds = $request->workstream_ids;
        $updates = $request->updates;

        $workstreams = Workstream::whereIn('id', $workstreamIds)->get();

        $updatedWorkstreams = [];
        foreach ($workstreams as $workstream) {
            $workstream->update($updates);
            $updatedWorkstreams[] = [
                'id' => $workstream->id,
                'status' => $workstream->status,
            ];
        }

        return response()->json([
            'data' => [
                'updated_count' => count($updatedWorkstreams),
                'updated_workstreams' => $updatedWorkstreams,
            ]
        ]);
    }

    /**
     * Check if the current user can access a workstream with the given permission.
     */
    private function userCanAccessWorkstream(Workstream $workstream, string $permissionType): bool
    {
        $userId = Auth::id();

        // Owner always has access
        if ($workstream->owner_id === $userId) {
            return true;
        }

        // Get effective permissions for the user
        $effectivePermissions = $workstream->getEffectivePermissionsForUser($userId);

        // Check if user has the required permission or higher
        $permissionHierarchy = ['view' => 1, 'edit' => 2, 'admin' => 3];
        $requiredLevel = $permissionHierarchy[$permissionType] ?? 0;

        foreach ($effectivePermissions['effective_permissions'] as $permission) {
            $userLevel = $permissionHierarchy[$permission] ?? 0;
            if ($userLevel >= $requiredLevel) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the current user owns a parent workstream in the hierarchy.
     */
    private function userOwnsParentWorkstream(Workstream $workstream): bool
    {
        $userId = Auth::id();
        $ancestors = $workstream->getAllAncestors();

        foreach ($ancestors as $ancestor) {
            if ($ancestor->owner_id === $userId) {
                return true;
            }
        }

        return false;
    }
}
