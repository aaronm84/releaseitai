<?php

namespace App\Http\Controllers;

use App\Models\Workstream;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WorkstreamsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Workstream::class, 'workstream');
    }

    public function index()
    {
        $workstreams = Workstream::with(['children', 'releases'])
            ->withCount(['releases', 'activeReleases'])
            ->get()
            ->map(function ($workstream) {
                // Calculate completion percentage based on releases
                $totalReleases = $workstream->releases_count;
                $completedReleases = $workstream->releases()
                    ->where('status', 'completed')
                    ->count();

                $completionPercentage = $totalReleases > 0
                    ? round(($completedReleases / $totalReleases) * 100)
                    : 0;

                return [
                    'id' => $workstream->id,
                    'name' => $workstream->name,
                    'description' => $workstream->description,
                    'type' => $workstream->type,
                    'status' => $workstream->status,
                    'parent_workstream_id' => $workstream->parent_workstream_id,
                    'hierarchy_depth' => $workstream->hierarchy_depth,
                    'active_releases_count' => $workstream->active_releases_count,
                    'total_releases_count' => $workstream->releases_count,
                    'completion_percentage' => $completionPercentage,
                    'created_at' => $workstream->created_at,
                    'updated_at' => $workstream->updated_at,
                ];
            });

        // Calculate insights for PM actionable data
        $totalWorkstreams = $workstreams->count();
        $activeWorkstreams = $workstreams->where('status', 'active')->count();
        $staleWorkstreams = $workstreams->filter(function ($workstream) {
            return $workstream['active_releases_count'] == 0 && $workstream['status'] == 'active';
        })->count();

        $insights = [
            'total_workstreams_count' => $totalWorkstreams,
            'active_workstreams_count' => $activeWorkstreams,
            'stale_workstreams_count' => $staleWorkstreams,
            'recommendations' => [
                'Create releases for stale workstreams',
                'Review completed workstreams for archival',
                'Update workstream descriptions for clarity'
            ]
        ];

        return Inertia::render('Workstreams/Index', [
            'workstreams' => $workstreams,
            'insights' => $insights,
            'uiConfig' => [
                'groupByType' => true,
                'showQuickActions' => true,
                'maxItemsPerPage' => 20,
                'adhd_optimized' => true,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => ['required', Rule::in(['product_line', 'initiative', 'experiment'])],
            'parent_workstream_id' => 'nullable|exists:workstreams,id',
            'status' => ['required', Rule::in(['draft', 'active', 'on_hold', 'completed', 'cancelled'])],
        ]);

        // Ensure parent_workstream_id exists in validated array
        if (!array_key_exists('parent_workstream_id', $validated)) {
            $validated['parent_workstream_id'] = null;
        }

        // Validate hierarchy rules
        if ($validated['type'] === 'product_line' && isset($validated['parent_workstream_id']) && $validated['parent_workstream_id']) {
            return redirect()->back()->withErrors([
                'parent_workstream_id' => 'Product lines cannot have a parent workstream.'
            ]);
        }

        if ($validated['type'] === 'initiative') {
            if (!$validated['parent_workstream_id']) {
                return redirect()->back()->withErrors([
                    'parent_workstream_id' => 'Initiatives must have a parent product line.'
                ]);
            }

            $parent = Workstream::find($validated['parent_workstream_id']);
            if ($parent->type !== 'product_line') {
                return redirect()->back()->withErrors([
                    'parent_workstream_id' => 'Initiatives can only be children of product lines.'
                ]);
            }
        }

        if ($validated['type'] === 'experiment') {
            if (!$validated['parent_workstream_id']) {
                return redirect()->back()->withErrors([
                    'parent_workstream_id' => 'Experiments must have a parent initiative.'
                ]);
            }

            $parent = Workstream::find($validated['parent_workstream_id']);
            if ($parent->type !== 'initiative') {
                return redirect()->back()->withErrors([
                    'parent_workstream_id' => 'Experiments can only be children of initiatives.'
                ]);
            }
        }

        // Calculate hierarchy depth
        $hierarchyDepth = 0;
        if ($validated['parent_workstream_id']) {
            $parent = Workstream::find($validated['parent_workstream_id']);
            $hierarchyDepth = $parent->hierarchy_depth + 1;
        }

        $validated['hierarchy_depth'] = $hierarchyDepth;
        $validated['owner_id'] = auth()->id();

        Workstream::create($validated);

        return redirect()->route('workstreams.index')->with('success', 'Workstream created successfully.');
    }

    public function show(Workstream $workstream)
    {
        $workstream->load([
            'children' => function ($query) {
                $query->withCount(['releases', 'activeReleases']);
            },
            'releases' => function ($query) {
                $query->with(['workstream'])
                    ->orderBy('target_date', 'asc');
            }
        ]);

        // Calculate completion percentage
        $totalReleases = $workstream->releases->count();
        $completedReleases = $workstream->releases->where('status', 'completed')->count();
        $completionPercentage = $totalReleases > 0
            ? round(($completedReleases / $totalReleases) * 100)
            : 0;

        // Calculate metrics
        $metrics = [
            'total_releases' => $totalReleases,
            'completed_releases' => $completedReleases,
            'active_releases' => $workstream->releases->whereIn('status', ['planned', 'in_progress'])->count(),
        ];

        return Inertia::render('Workstreams/Show', [
            'workstream' => [
                'id' => $workstream->id,
                'name' => $workstream->name,
                'description' => $workstream->description,
                'type' => $workstream->type,
                'status' => $workstream->status,
                'parent_workstream_id' => $workstream->parent_workstream_id,
                'hierarchy_depth' => $workstream->hierarchy_depth,
                'completion_percentage' => $completionPercentage,
                'children' => $workstream->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'description' => $child->description,
                        'type' => $child->type,
                        'status' => $child->status,
                        'active_releases_count' => $child->active_releases_count,
                        'total_releases_count' => $child->releases_count,
                    ];
                }),
                'releases' => $workstream->releases->map(function ($release) {
                    return [
                        'id' => $release->id,
                        'name' => $release->name,
                        'description' => $release->description,
                        'status' => $release->status,
                        'target_date' => $release->target_date,
                        'workstream_name' => $release->workstream->name,
                    ];
                }),
                'metrics' => $metrics,
                'created_at' => $workstream->created_at,
                'updated_at' => $workstream->updated_at,
            ]
        ]);
    }

    public function update(Request $request, Workstream $workstream)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => ['required', Rule::in(['product_line', 'initiative', 'experiment'])],
            'parent_workstream_id' => 'nullable|exists:workstreams,id',
            'status' => ['required', Rule::in(['draft', 'active', 'on_hold', 'completed', 'cancelled'])],
        ]);

        // Validate hierarchy rules (same as store method)
        if ($validated['type'] === 'product_line' && $validated['parent_workstream_id']) {
            return redirect()->back()->withErrors([
                'parent_workstream_id' => 'Product lines cannot have a parent workstream.'
            ]);
        }

        if ($validated['type'] === 'initiative') {
            if (!$validated['parent_workstream_id']) {
                return redirect()->back()->withErrors([
                    'parent_workstream_id' => 'Initiatives must have a parent product line.'
                ]);
            }

            $parent = Workstream::find($validated['parent_workstream_id']);
            if ($parent->type !== 'product_line') {
                return redirect()->back()->withErrors([
                    'parent_workstream_id' => 'Initiatives can only be children of product lines.'
                ]);
            }
        }

        if ($validated['type'] === 'experiment') {
            if (!$validated['parent_workstream_id']) {
                return redirect()->back()->withErrors([
                    'parent_workstream_id' => 'Experiments must have a parent initiative.'
                ]);
            }

            $parent = Workstream::find($validated['parent_workstream_id']);
            if ($parent->type !== 'initiative') {
                return redirect()->back()->withErrors([
                    'parent_workstream_id' => 'Experiments can only be children of initiatives.'
                ]);
            }
        }

        // Prevent circular references
        if ($validated['parent_workstream_id'] && $this->wouldCreateCircularReference($workstream->id, $validated['parent_workstream_id'])) {
            return redirect()->back()->withErrors([
                'parent_workstream_id' => 'This would create a circular reference in the hierarchy.'
            ]);
        }

        // Recalculate hierarchy depth
        $hierarchyDepth = 0;
        if ($validated['parent_workstream_id']) {
            $parent = Workstream::find($validated['parent_workstream_id']);
            $hierarchyDepth = $parent->hierarchy_depth + 1;
        }

        $validated['hierarchy_depth'] = $hierarchyDepth;

        $workstream->update($validated);

        // Update hierarchy depth for all descendants
        $this->updateDescendantHierarchyDepth($workstream);

        return redirect()->route('workstreams.index')->with('success', 'Workstream updated successfully.');
    }

    public function destroy(Workstream $workstream)
    {
        // Check if workstream has children
        if ($workstream->children()->count() > 0) {
            return redirect()->back()->withErrors([
                'delete' => 'Cannot delete workstream with child workstreams. Please delete children first.'
            ]);
        }

        // Check if workstream has releases
        if ($workstream->releases()->count() > 0) {
            return redirect()->back()->withErrors([
                'delete' => 'Cannot delete workstream with releases. Please delete releases first.'
            ]);
        }

        $workstream->delete();

        return redirect()->route('workstreams.index')->with('success', 'Workstream deleted successfully.');
    }

    /**
     * Check if setting the parent would create a circular reference
     */
    private function wouldCreateCircularReference($workstreamId, $parentId)
    {
        $visited = [];
        $current = $parentId;

        while ($current && !in_array($current, $visited)) {
            if ($current == $workstreamId) {
                return true;
            }

            $visited[] = $current;
            $parent = Workstream::find($current);
            $current = $parent ? $parent->parent_workstream_id : null;
        }

        return false;
    }

    /**
     * Update hierarchy depth for all descendants
     */
    private function updateDescendantHierarchyDepth(Workstream $workstream)
    {
        $children = $workstream->children;

        foreach ($children as $child) {
            $child->update(['hierarchy_depth' => $workstream->hierarchy_depth + 1]);
            $this->updateDescendantHierarchyDepth($child);
        }
    }
}