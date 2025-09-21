<?php

namespace App\Http\Controllers;

use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ContentController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Get user's content with optional filtering
        $query = Content::where('user_id', $user->id)
            ->with(['actionItems'])
            ->orderBy('created_at', 'desc');

        // Filter by type if specified
        if ($request->has('type')) {
            if ($request->type === 'brain_dump') {
                // Special case: filter by brain_dump tag for brain dumps
                $query->whereJsonContains('tags', 'brain_dump');
            } else {
                // Normal case: filter by actual type
                $query->where('type', $request->type);
            }
        }

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                  ->orWhere('content', 'ILIKE', "%{$search}%");
            });
        }

        $content = $query->paginate(20);

        // Transform the data for the frontend
        $content->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'content' => $item->content,
                'type' => $item->type,
                'status' => $item->status,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'tags' => $item->tags,
                'action_items_count' => $item->actionItems ? $item->actionItems->count() : 0,
                'preview' => strlen($item->content) > 200 ? substr($item->content, 0, 200) . '...' : $item->content
            ];
        });

        return Inertia::render('Content/Index', [
            'content' => $content,
            'filters' => [
                'type' => $request->type,
                'search' => $request->search
            ],
            'stats' => [
                'total_items' => Content::where('user_id', $user->id)->count(),
                'brain_dumps' => Content::where('user_id', $user->id)->whereJsonContains('tags', 'brain_dump')->count(),
                'processed_items' => Content::where('user_id', $user->id)->where('status', 'processed')->count(),
            ]
        ]);
    }

    public function show(Content $content)
    {
        // Ensure user can only view their own content
        if ($content->user_id !== Auth::id()) {
            abort(404);
        }

        // Load relationships
        $content->load(['actionItems', 'stakeholders', 'workstreams', 'releases']);

        return Inertia::render('Content/Show', [
            'content' => [
                'id' => $content->id,
                'title' => $content->title,
                'content' => $content->content,
                'type' => $content->type,
                'status' => $content->status,
                'created_at' => $content->created_at,
                'updated_at' => $content->updated_at,
                'tags' => $content->tags,
                'ai_summary' => $content->ai_summary,
                'action_items' => $content->actionItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'action_text' => $item->action_text,
                        'priority' => $item->priority,
                        'status' => $item->status,
                        'due_date' => $item->due_date,
                        'assignee_stakeholder_id' => $item->assignee_stakeholder_id,
                    ];
                }),
                'stakeholders' => $content->stakeholders,
                'workstreams' => $content->workstreams,
                'releases' => $content->releases,
            ]
        ]);
    }

    public function destroy(Content $content)
    {
        // Ensure user can only delete their own content
        if ($content->user_id !== Auth::id()) {
            abort(404);
        }

        $content->delete();

        return redirect()->route('content.index')->with('success', 'Content deleted successfully.');
    }
}