<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\Workstream;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // If no user is authenticated, redirect to login
        if (!$user) {
            return redirect()->route('login');
        }

        // Get top 3 priorities based on urgency and due dates
        $topPriorities = Release::with(['workstream:id,name'])
            ->whereHas('workstream', function ($workstreamQuery) use ($user) {
                $workstreamQuery->where('owner_id', $user->id);
            })
            ->whereIn('status', ['planned', 'in_progress'])
            ->whereNotNull('target_date')
            ->whereNotNull('workstream_id')
            ->orderBy('target_date', 'asc')
            ->limit(3)
            ->get()
            ->map(function ($release) {
                $dueDate = Carbon::parse($release->target_date);
                $daysDiff = now()->diffInDays($dueDate, false);

                // For testing: if it's less than 1 day, round up to 1
                $daysUntilDue = $daysDiff;
                if ($daysUntilDue < 1 && $daysUntilDue > 0) {
                    $daysUntilDue = 1;
                }

                return [
                    'id' => $release->id,
                    'name' => $release->name,
                    'workstream_name' => $release->workstream ? $release->workstream->name : 'Unknown Workstream',
                    'status' => $release->status,
                    'planned_date' => $release->target_date,
                    'due_in_days' => max(0, (int) $daysUntilDue), // Days until due date, 0 if overdue
                ];
            });

        // Get workstreams overview with release counts
        $workstreams = Workstream::where('owner_id', $user->id)
            ->with('releases')
            ->get()
            ->map(function ($workstream) {
                $totalReleases = $workstream->releases->count();
                $activeReleases = $workstream->releases->where('status', 'in_progress')->count();
                $completedReleases = $workstream->releases->where('status', 'completed')->count();

                return [
                    'id' => $workstream->id,
                    'name' => $workstream->name,
                    'type' => $workstream->type,
                    'active_releases_count' => $activeReleases,
                    'total_releases_count' => $totalReleases,
                    'completion_percentage' => $totalReleases > 0 ? round(($completedReleases / $totalReleases) * 100) : 0,
                ];
            });

        // Quick Add configuration for ADHD users
        $quickAddConfig = [
            'enabled' => true,
            'placeholder' => 'Paste meeting notes, emails, or ideas...',
            'autoSave' => true,
            'processingDelay' => 500, // 500ms delay for ADHD users
        ];

        // Morning brief (placeholder for AI-generated content)
        $morningBrief = [
            'summary' => 'You have 3 active releases and 2 upcoming deadlines this week.',
            'highlights' => [
                'Login Flow release is due in 2 days',
                'Payment Integration planning meeting scheduled for tomorrow',
                '5 stakeholder communications pending response',
            ],
        ];

        return Inertia::render('Dashboard/Index', [
            'topPriorities' => $topPriorities,
            'workstreams' => $workstreams,
            'user' => $user,
            'quickAddConfig' => $quickAddConfig,
            'morningBrief' => $morningBrief,
            'auth' => [
                'user' => $user,
            ],
        ]);
    }
}