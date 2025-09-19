<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdateWorkstreamRequest;
use App\Http\Requests\IndexWorkstreamRequest;
use App\Http\Requests\MoveWorkstreamRequest;
use App\Http\Requests\StoreWorkstreamPermissionRequest;
use App\Http\Requests\StoreWorkstreamRequest;
use App\Http\Requests\UpdateWorkstreamRequest;
use App\Http\Resources\WorkstreamResource;
use App\Models\Workstream;
use App\Services\PaginationService;
use App\Services\WorkstreamService;
use Illuminate\Http\JsonResponse;

class WorkstreamController extends Controller
{
    public function __construct(
        private WorkstreamService $workstreamService,
        private PaginationService $paginationService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexWorkstreamRequest $request): JsonResponse
    {
        $filters = $request->only(['type', 'status', 'parent_workstream_id']);
        $perPage = $request->get('per_page', 50);

        $workstreams = $this->workstreamService->getWorkstreams($filters, $perPage);

        return $this->paginationService->jsonResponse($workstreams);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWorkstreamRequest $request): JsonResponse
    {
        try {
            $workstream = $this->workstreamService->createWorkstream($request->validated());

            return response()->json([
                'data' => new WorkstreamResource($workstream)
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'errors' => [
                    'parent_workstream_id' => [$e->getMessage()]
                ]
            ], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Workstream $workstream): JsonResponse
    {
        $workstream = $this->workstreamService->getWorkstream($workstream, 'view');

        if (!$workstream) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => new WorkstreamResource($workstream)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWorkstreamRequest $request, Workstream $workstream): JsonResponse
    {
        $updatedWorkstream = $this->workstreamService->updateWorkstream($workstream, $request->validated());

        if (!$updatedWorkstream) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => new WorkstreamResource($updatedWorkstream)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Workstream $workstream): JsonResponse
    {
        if (!$this->workstreamService->deleteWorkstream($workstream)) {
            return response()->json([
                'message' => 'Cannot delete workstream with child workstreams. Move or delete children first.'
            ], 422);
        }

        return response()->json(null, 204);
    }

    /**
     * Get the hierarchy tree for a workstream.
     */
    public function hierarchy(Workstream $workstream): JsonResponse
    {
        $hierarchy = $this->workstreamService->getHierarchy($workstream);

        if ($hierarchy === null) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => $hierarchy
        ]);
    }

    /**
     * Get rollup report for a workstream.
     */
    public function rollupReport(Workstream $workstream): JsonResponse
    {
        $report = $this->workstreamService->getRollupReport($workstream);

        if ($report === null) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => $report
        ]);
    }

    /**
     * Get permissions for a workstream.
     */
    public function permissions(Workstream $workstream): JsonResponse
    {
        $permissions = $this->workstreamService->getPermissions($workstream);

        if ($permissions === null) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => $permissions
        ]);
    }

    /**
     * Grant permissions on a workstream.
     */
    public function storePermissions(StoreWorkstreamPermissionRequest $request, Workstream $workstream): JsonResponse
    {
        $permission = $this->workstreamService->grantPermissions($workstream, $request->validated());

        if (!$permission) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

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
        $result = $this->workstreamService->moveWorkstream($workstream, $request->new_parent_workstream_id);

        if (!$result['success']) {
            return response()->json([
                'errors' => [
                    'new_parent_workstream_id' => [$result['error']]
                ]
            ], 422);
        }

        $workstream = $result['workstream'];

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
        $result = $this->workstreamService->bulkUpdateWorkstreams(
            $request->workstream_ids,
            $request->updates
        );

        return response()->json([
            'data' => $result
        ]);
    }

}
