<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Jobs\ProcessUploadedFile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Content::where('user_id', $request->user()->id)
            ->with(['stakeholders', 'workstreams', 'releases', 'actionItems']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('title', 'ILIKE', $search)
                  ->orWhere('description', 'ILIKE', $search)
                  ->orWhere('content', 'ILIKE', $search);
            });
        }

        $content = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'data' => $content->items(),
            'meta' => [
                'current_page' => $content->currentPage(),
                'total' => $content->total(),
                'per_page' => $content->perPage(),
                'last_page' => $content->lastPage(),
                'from' => $content->firstItem(),
                'to' => $content->lastItem(),
            ],
            'links' => [
                'first' => $content->url(1),
                'last' => $content->url($content->lastPage()),
                'prev' => $content->previousPageUrl(),
                'next' => $content->nextPageUrl(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ];

        // Add file validation if no content is provided
        if (!$request->has('content') || empty($request->content)) {
            $rules['file'] = 'required|file|mimes:txt,pdf,doc,docx|max:10240';
        } else {
            $rules['file'] = 'nullable|file|mimes:txt,pdf,doc,docx|max:10240';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'tags' => $request->tags ?? [],
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('uploads', 'local');

            $data['file_path'] = $path;
            $data['file_type'] = $file->getClientOriginalExtension();
            $data['file_size'] = $file->getSize();
            $data['status'] = 'pending';
            $data['type'] = 'file';
        } elseif ($request->content) {
            $data['content'] = $request->content;
            $data['status'] = 'processing';
            $data['type'] = 'manual';
        }

        $content = Content::create($data);

        if ($content->status === 'uploaded') {
            ProcessUploadedFile::dispatch($content);
        }

        return response()->json($content->load(['stakeholders', 'workstreams', 'releases', 'actionItems']), 201);
    }

    public function show(Request $request, Content $content): JsonResponse
    {
        if ($content->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($content->load(['stakeholders', 'workstreams', 'releases', 'actionItems']));
    }

    public function update(Request $request, Content $content): JsonResponse
    {
        if ($content->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $content->update($request->only(['title', 'description', 'tags']));

        return response()->json($content->load(['stakeholders', 'workstreams', 'releases', 'actionItems']));
    }

    public function destroy(Request $request, Content $content): JsonResponse
    {
        if ($content->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($content->file_path && Storage::disk('local')->exists($content->file_path)) {
            Storage::disk('local')->delete($content->file_path);
        }

        $content->delete();

        return response()->json(null, 204);
    }

    public function reprocess(Request $request, Content $content): JsonResponse
    {
        if ($content->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (in_array($content->status, ['processed', 'processing'])) {
            return response()->json([
                'message' => 'Content is already processed or currently processing'
            ], 422);
        }

        // Use save() instead of update() to ensure the change is committed
        $content->status = 'processing';
        $content->save();

        ProcessUploadedFile::dispatch($content);

        return response()->json([
            'message' => 'Content reprocessing started',
            'content' => [
                'id' => $content->id,
                'status' => $content->status
            ]
        ]);
    }

    public function analysis(Request $request, Content $content): JsonResponse
    {
        if ($content->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($content->status !== 'processed') {
            return response()->json([
                'message' => 'Content has not been processed yet'
            ], 422);
        }

        return response()->json([
            'content_id' => $content->id,
            'ai_summary' => $content->ai_summary,
            'stakeholders' => $content->stakeholders()->get()->map(function ($stakeholder) {
                return [
                    'id' => $stakeholder->id,
                    'name' => $stakeholder->name,
                    'mention_type' => $stakeholder->pivot->mention_type ?? 'direct',
                    'confidence_score' => $stakeholder->pivot->confidence_score ?? 0.8,
                    'context' => $stakeholder->pivot->context ?? '',
                ];
            }),
            'workstreams' => $content->workstreams()->get()->map(function ($workstream) {
                return [
                    'id' => $workstream->id,
                    'name' => $workstream->name,
                    'relevance_type' => $workstream->pivot->relevance_type ?? 'mentioned',
                    'confidence_score' => $workstream->pivot->confidence_score ?? 0.8,
                    'context' => $workstream->pivot->context ?? '',
                ];
            }),
            'releases' => $content->releases()->get()->map(function ($release) {
                return [
                    'id' => $release->id,
                    'name' => $release->name,
                    'version' => $release->version,
                    'relevance_type' => $release->pivot->relevance_type ?? 'mentioned',
                    'confidence_score' => $release->pivot->confidence_score ?? 0.8,
                    'context' => $release->pivot->context ?? '',
                ];
            }),
            'action_items' => $content->actionItems()->get()->map(function ($actionItem) {
                return [
                    'id' => $actionItem->id,
                    'action_text' => $actionItem->action_text,
                    'priority' => $actionItem->priority,
                    'status' => $actionItem->status,
                    'assignee_stakeholder_id' => $actionItem->assignee_stakeholder_id,
                    'due_date' => $actionItem->due_date,
                    'confidence_score' => $actionItem->confidence_score,
                ];
            }),
        ]);
    }
}