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

        // Get stakeholder insights
        $stakeholderData = $this->getStakeholderInsights($user);

        // Time-aware brief (morning brief only during morning hours)
        $timeAwareBrief = $this->generateTimeAwareBrief($topPriorities, $workstreams, $stakeholderData);


        return Inertia::render('Dashboard/Index', [
            'topPriorities' => $topPriorities,
            'workstreams' => $workstreams,
            'stakeholderData' => $stakeholderData,
            'user' => $user,
            'quickAddConfig' => $quickAddConfig,
            'morningBrief' => $timeAwareBrief,
            'auth' => [
                'user' => $user,
            ],
        ]);
    }

    private function getStakeholderInsights($user)
    {
        // Get all users except the current user (potential stakeholders)
        $allStakeholders = User::where('id', '!=', $user->id)->get();

        $totalStakeholders = $allStakeholders->count();
        $needsFollowUp = 0;
        $recentlyContacted = [];
        $overdueContacts = [];

        foreach ($allStakeholders as $stakeholder) {
            // Calculate days since last contact
            $daysSinceContact = $stakeholder->last_contact_at
                ? $stakeholder->last_contact_at->diffInDays(now())
                : null;

            // Check if needs follow-up based on communication frequency
            if ($this->stakeholderNeedsFollowUp($stakeholder, $daysSinceContact)) {
                $needsFollowUp++;
                $overdueContacts[] = [
                    'id' => $stakeholder->id,
                    'name' => $stakeholder->name,
                    'days_overdue' => $daysSinceContact,
                    'frequency' => $stakeholder->communication_frequency,
                ];
            }

            // Track recently contacted (within last 7 days)
            if ($daysSinceContact !== null && $daysSinceContact <= 7) {
                $recentlyContacted[] = [
                    'id' => $stakeholder->id,
                    'name' => $stakeholder->name,
                    'last_contact_at' => $stakeholder->last_contact_at,
                    'channel' => $stakeholder->last_contact_channel,
                ];
            }
        }

        // Sort overdue contacts by days overdue (most overdue first)
        usort($overdueContacts, function ($a, $b) {
            return $b['days_overdue'] <=> $a['days_overdue'];
        });

        // Sort recently contacted by most recent first
        usort($recentlyContacted, function ($a, $b) {
            return $b['last_contact_at'] <=> $a['last_contact_at'];
        });

        return [
            'total_stakeholders' => $totalStakeholders,
            'needs_follow_up' => $needsFollowUp,
            'recently_contacted' => array_slice($recentlyContacted, 0, 5), // Top 5 recent
            'overdue_contacts' => array_slice($overdueContacts, 0, 5), // Top 5 overdue
            'response_rate' => $totalStakeholders > 0 ? (($totalStakeholders - $needsFollowUp) / $totalStakeholders) * 100 : 100,
        ];
    }

    private function stakeholderNeedsFollowUp($stakeholder, $daysSinceContact): bool
    {
        if (!$stakeholder->communication_frequency || !$daysSinceContact) {
            return false;
        }

        $thresholds = [
            'daily' => 1,
            'weekly' => 7,
            'biweekly' => 14,
            'monthly' => 30,
            'as_needed' => null, // No automatic follow-up needed
        ];

        $threshold = $thresholds[$stakeholder->communication_frequency] ?? null;

        return $threshold && $daysSinceContact > $threshold;
    }

    private function generateTimeAwareBrief($topPriorities, $workstreams, $stakeholderData)
    {
        $user = Auth::user();
        $userTimezone = $user->timezone ?? config('app.timezone');
        $hour = now()->setTimezone($userTimezone)->hour;

        $briefData = $this->generateBriefContent($topPriorities, $workstreams, $stakeholderData);

        // Determine title and adjust content based on time of day
        if ($hour >= 6 && $hour < 12) {
            $briefData['title'] = 'Morning Brief';
            $briefData['icon'] = '🌅';
        } elseif ($hour >= 12 && $hour < 17) {
            $briefData['title'] = 'Afternoon Update';
            $briefData['icon'] = '☀️';
        } else {
            // Evening/Night hours (17:00-05:59)
            $briefData['title'] = 'Evening Review';
            $briefData['icon'] = '🌆';

            // Add end-of-day context to evening content
            $briefData = $this->addEveningContext($briefData);
        }

        return $briefData;
    }

    private function generateBriefContent($topPriorities, $workstreams, $stakeholderData)
    {
        $activeReleases = 0;
        $upcomingDeadlines = 0;
        $highlights = [];

        // Count active releases across all workstreams
        foreach ($workstreams as $workstream) {
            $activeReleases += $workstream['active_releases_count'];
        }

        // Count upcoming deadlines (releases due in next 7 days)
        foreach ($topPriorities as $priority) {
            if ($priority['due_in_days'] <= 7) {
                $upcomingDeadlines++;
            }
        }

        // Generate summary
        if ($activeReleases === 0 && $workstreams->count() === 0) {
            $summary = "Welcome to ReleaseIt! Ready to create your first workstream and start managing releases.";
        } elseif ($activeReleases === 0) {
            $summary = "No active releases today. Perfect time to plan your next release or review completed work.";
        } else {
            $workstreamText = $workstreams->count() === 1 ? 'workstream' : 'workstreams';
            $releaseText = $activeReleases === 1 ? 'active release' : 'active releases';
            $deadlineText = $upcomingDeadlines === 0 ? 'no upcoming deadlines' :
                ($upcomingDeadlines === 1 ? '1 upcoming deadline' : "{$upcomingDeadlines} upcoming deadlines");

            $summary = "You have {$activeReleases} {$releaseText} across {$workstreams->count()} {$workstreamText} with {$deadlineText} this week.";
        }

        // Generate highlights
        if ($topPriorities->count() > 0) {
            $firstPriority = $topPriorities->first();
            if ($firstPriority['due_in_days'] === 0) {
                $highlights[] = "🔴 {$firstPriority['name']} is due today";
            } elseif ($firstPriority['due_in_days'] === 1) {
                $highlights[] = "🟡 {$firstPriority['name']} is due tomorrow";
            } elseif ($firstPriority['due_in_days'] <= 7) {
                $highlights[] = "📅 {$firstPriority['name']} is due in {$firstPriority['due_in_days']} days";
            }
        }

        // Add workstream progress highlight
        if ($workstreams->count() > 0) {
            $avgCompletion = $workstreams->avg('completion_percentage');
            if ($avgCompletion >= 80) {
                $highlights[] = "🎉 Your workstreams are " . round($avgCompletion) . "% complete on average";
            } elseif ($avgCompletion >= 50) {
                $highlights[] = "📈 Your workstreams are " . round($avgCompletion) . "% complete on average";
            }
        }

        // Add stakeholder highlight
        if ($stakeholderData['needs_follow_up'] > 0) {
            $stakeholderText = $stakeholderData['needs_follow_up'] === 1 ? 'stakeholder needs' : 'stakeholders need';
            $highlights[] = "💬 {$stakeholderData['needs_follow_up']} {$stakeholderText} follow-up";
        } elseif ($stakeholderData['total_stakeholders'] > 0) {
            $highlights[] = "✅ All stakeholder communications up to date";
        }

        // If no highlights, add encouraging message
        if (empty($highlights)) {
            $highlights[] = "Ready to tackle today's challenges!";
        }

        return [
            'summary' => $summary,
            'highlights' => $highlights,
        ];
    }

    private function addEveningContext($briefData)
    {
        // Add evening-specific highlights
        $eveningHighlights = [
            "🎯 Review today's progress and plan for tomorrow",
        ];

        // Prepend evening context to existing highlights
        $briefData['highlights'] = array_merge($eveningHighlights, $briefData['highlights']);

        return $briefData;
    }

}