<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\ReleaseTask;
use App\Models\Communication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ReleaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Release::class, 'release');
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $releases = Release::with(['workstream:id,name', 'tasks'])
            ->whereHas('workstream', function ($query) use ($user) {
                $query->where('owner_id', $user->id);
            })
            ->orderBy('target_date', 'asc')
            ->get()
            ->map(function ($release) {
                return [
                    'id' => $release->id,
                    'name' => $release->name,
                    'status' => $release->status,
                    'target_date' => $release->target_date,
                    'workstream_name' => $release->workstream?->name,
                    'tasks_count' => $release->tasks->count(),
                    'completed_tasks_count' => $release->tasks->where('status', 'completed')->count(),
                ];
            });

        return Inertia::render('Releases/Index', [
            'releases' => $releases,
            'user' => $user,
        ]);
    }

    public function show(Release $release)
    {
        $user = Auth::user();

        // Load release with all related data
        $release->load([
            'workstream:id,name',
            'tasks' => function ($query) {
                $query->orderBy('order', 'asc')->orderBy('created_at', 'asc');
            },
            'tasks.assignedUser:id,name',
            'communications' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ]);

        // Calculate metrics
        $totalTasks = $release->tasks->count();
        $completedTasks = $release->tasks->where('status', 'completed')->count();
        $blockedTasks = $release->tasks->where('status', 'blocked')->count();
        $inProgressTasks = $release->tasks->where('status', 'in_progress')->count();

        $progressPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        // Get dynamic checklist based on release type
        $checklistTemplate = $this->getChecklistTemplate($release->type ?? 'feature');

        return Inertia::render('Releases/Hub', [
            'release' => [
                'id' => $release->id,
                'name' => $release->name,
                'description' => $release->description,
                'status' => $release->status,
                'target_date' => $release->target_date,
                'workstream' => $release->workstream,
                'tasks' => $release->tasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'type' => $task->type,
                        'status' => $task->status,
                        'priority' => $task->priority,
                        'due_date' => $task->due_date,
                        'is_blocker' => $task->is_blocker,
                        'notes' => $task->notes,
                        'assigned_user' => $task->assignedUser,
                    ];
                }),
                'communications' => $release->communications,
                'metrics' => [
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'blocked_tasks' => $blockedTasks,
                    'in_progress_tasks' => $inProgressTasks,
                    'progress_percentage' => $progressPercentage,
                ],
            ],
            'checklistTemplate' => $checklistTemplate,
            'user' => $user,
        ]);
    }

    public function updateStatus(Release $release, Request $request)
    {
        $request->validate([
            'status' => 'required|in:planned,in_progress,completed,cancelled,on_hold',
            'notes' => 'nullable|string',
        ]);

        $release->update([
            'status' => $request->status,
        ]);

        // Log status change in communications
        Communication::create([
            'release_id' => $release->id,
            'type' => 'status_update',
            'title' => "Status updated to {$request->status}",
            'content' => $request->notes ?? "Release status changed to {$request->status}",
            'created_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Release status updated successfully.');
    }

    public function storeTasks(Release $release, Request $request)
    {
        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.title' => 'required|string|max:255',
            'tasks.*.description' => 'nullable|string',
            'tasks.*.type' => 'required|in:development,testing,documentation,stakeholder,deployment,custom',
            'tasks.*.priority' => 'required|in:low,medium,high,critical',
            'tasks.*.due_date' => 'nullable|date',
            'tasks.*.assigned_to' => 'nullable|exists:users,id',
        ]);

        $tasks = [];
        foreach ($request->tasks as $index => $taskData) {
            $task = ReleaseTask::create([
                'release_id' => $release->id,
                'title' => $taskData['title'],
                'description' => $taskData['description'] ?? null,
                'type' => $taskData['type'],
                'priority' => $taskData['priority'],
                'due_date' => $taskData['due_date'] ?? null,
                'assigned_to' => $taskData['assigned_to'] ?? null,
                'order' => $index + 1,
            ]);
            $tasks[] = $task;
        }

        return redirect()->back()->with('success', count($tasks) . ' tasks added successfully.');
    }

    public function bulkUpdateTasks(Release $release, Request $request)
    {
        $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:release_tasks,id',
            'action' => 'required|in:complete,delete,update_status',
            'status' => 'required_if:action,update_status|in:pending,in_progress,completed,blocked',
        ]);

        $tasks = ReleaseTask::whereIn('id', $request->task_ids)
            ->where('release_id', $release->id)
            ->get();

        $updatedCount = 0;
        foreach ($tasks as $task) {
            switch ($request->action) {
                case 'complete':
                    $task->update(['status' => 'completed']);
                    $updatedCount++;
                    break;
                case 'delete':
                    $task->delete();
                    $updatedCount++;
                    break;
                case 'update_status':
                    $task->update(['status' => $request->status]);
                    $updatedCount++;
                    break;
            }
        }

        return redirect()->back()->with('success', "{$updatedCount} tasks updated successfully.");
    }

    public function stakeholders(Release $release)
    {
        $user = Auth::user();

        // Load release with stakeholder data
        $release->load([
            'stakeholders' => function ($query) {
                $query->orderBy('stakeholder_releases.created_at', 'desc');
            },
            'communications' => function ($query) {
                $query->with(['participants' => function ($pQuery) {
                    $pQuery->with('user:id,name,email');
                }])
                ->orderBy('created_at', 'desc')
                ->limit(10);
            }
        ]);

        // Calculate engagement metrics
        $totalStakeholders = $release->stakeholders->count();
        $recentCommunications = $release->communications;

        $responseRate = 0;
        $avgResponseTime = 0;

        if ($recentCommunications->count() > 0) {
            $totalResponses = 0;
            $totalResponseTimes = [];

            foreach ($recentCommunications as $communication) {
                $responded = $communication->participants->where('delivery_status', 'responded')->count();
                $totalResponses += $responded;

                foreach ($communication->participants->where('delivery_status', 'responded') as $participant) {
                    if ($participant->delivered_at && $participant->read_at) {
                        $responseTime = $participant->delivered_at->diffInHours($participant->read_at);
                        $totalResponseTimes[] = $responseTime;
                    }
                }
            }

            $totalParticipants = $recentCommunications->sum(function ($comm) {
                return $comm->participants->count();
            });

            $responseRate = $totalParticipants > 0 ? ($totalResponses / $totalParticipants) * 100 : 0;
            $avgResponseTime = count($totalResponseTimes) > 0 ? array_sum($totalResponseTimes) / count($totalResponseTimes) : 0;
        }

        // Group stakeholders by role
        $stakeholdersByRole = $release->stakeholders->groupBy('pivot.role')->map(function ($stakeholders, $role) {
            return [
                'role' => $role,
                'count' => $stakeholders->count(),
                'stakeholders' => $stakeholders->map(function ($stakeholder) {
                    return [
                        'id' => $stakeholder->id,
                        'name' => $stakeholder->name,
                        'email' => $stakeholder->email,
                        'role' => $stakeholder->pivot->role,
                        'notification_preference' => $stakeholder->pivot->notification_preference,
                        'added_at' => $stakeholder->pivot->created_at,
                    ];
                })
            ];
        })->values();

        return Inertia::render('Releases/Stakeholders', [
            'release' => [
                'id' => $release->id,
                'name' => $release->name,
                'status' => $release->status,
                'target_date' => $release->target_date,
            ],
            'stakeholders' => $release->stakeholders->map(function ($stakeholder) {
                return [
                    'id' => $stakeholder->id,
                    'name' => $stakeholder->name,
                    'email' => $stakeholder->email,
                    'role' => $stakeholder->pivot->role,
                    'notification_preference' => $stakeholder->pivot->notification_preference,
                    'added_at' => $stakeholder->pivot->created_at,
                ];
            }),
            'stakeholdersByRole' => $stakeholdersByRole,
            'engagementMetrics' => [
                'total_stakeholders' => $totalStakeholders,
                'response_rate' => round($responseRate, 1),
                'avg_response_time_hours' => round($avgResponseTime, 1),
                'recent_interactions' => $recentCommunications->count(),
                'by_channel' => $recentCommunications->groupBy('channel')->map->count(),
            ],
            'recentCommunications' => $recentCommunications->map(function ($communication) {
                return [
                    'id' => $communication->id,
                    'subject' => $communication->subject,
                    'channel' => $communication->channel,
                    'created_at' => $communication->created_at,
                    'participants_count' => $communication->participants->count(),
                    'responded_count' => $communication->participants->where('delivery_status', 'responded')->count(),
                ];
            }),
            'user' => $user,
        ]);
    }

    public function storeStakeholder(Release $release, Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|in:owner,reviewer,approver,observer',
            'notification_preference' => 'required|in:email,slack,none',
        ]);

        // Find the user by email
        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors(['email' => 'User not found with this email address.']);
        }

        // Check if stakeholder already exists for this release
        if ($release->stakeholders()->where('user_id', $user->id)->exists()) {
            return back()->withErrors(['email' => 'This user is already a stakeholder for this release.']);
        }

        // Add stakeholder to release
        $release->stakeholders()->attach($user->id, [
            'role' => $request->role,
            'notification_preference' => $request->notification_preference,
        ]);

        return redirect()->back()->with('success', 'Stakeholder added successfully.');
    }

    public function storeCommunication(Release $release, Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'channel' => 'required|in:email,slack,teams,phone',
            'priority' => 'required|in:low,normal,high,urgent',
            'communication_type' => 'required|in:update,approval_request,notification,reminder',
            'recipient_roles' => 'array',
            'recipient_roles.*' => 'in:owner,reviewer,approver,observer',
            'specific_recipients' => 'array',
            'specific_recipients.*' => 'exists:users,id',
        ]);

        // Create the communication
        $communication = \App\Models\Communication::create([
            'release_id' => $release->id,
            'subject' => $request->subject,
            'content' => $request->content,
            'channel' => $request->channel,
            'direction' => 'outgoing',
            'communication_date' => now(),
            'priority' => $request->priority,
            'communication_type' => $request->communication_type,
            'initiated_by_user_id' => Auth::id(),
        ]);

        // Add participants based on roles and specific recipients
        $participants = collect();

        // Add stakeholders by role
        if (!empty($request->recipient_roles)) {
            foreach ($request->recipient_roles as $role) {
                $roleStakeholders = $release->stakeholdersByRole($role)->get();
                $participants = $participants->merge($roleStakeholders);
            }
        }

        // Add specific recipients
        if (!empty($request->specific_recipients)) {
            $specificUsers = \App\Models\User::whereIn('id', $request->specific_recipients)->get();
            $participants = $participants->merge($specificUsers);
        }

        // Remove duplicates and add to communication participants
        $participants = $participants->unique('id');

        foreach ($participants as $participant) {
            \App\Models\CommunicationParticipant::create([
                'communication_id' => $communication->id,
                'user_id' => $participant->id,
                'role' => $participant->pivot->role ?? 'recipient',
                'delivery_status' => 'pending',
            ]);

            // Update last contact tracking for each participant
            $participant->update([
                'last_contact_at' => now(),
                'last_contact_channel' => $request->channel,
            ]);
        }

        return redirect()->back()->with('success', "Communication sent to {$participants->count()} stakeholders.");
    }

    private function getChecklistTemplate(string $type): array
    {
        $templates = [
            'feature' => [
                ['title' => 'Requirements Review', 'type' => 'documentation'],
                ['title' => 'Design Approval', 'type' => 'stakeholder'],
                ['title' => 'Development Complete', 'type' => 'development'],
                ['title' => 'Code Review', 'type' => 'development'],
                ['title' => 'Unit Tests', 'type' => 'testing'],
                ['title' => 'Integration Tests', 'type' => 'testing'],
                ['title' => 'QA Testing', 'type' => 'testing'],
                ['title' => 'Documentation Updated', 'type' => 'documentation'],
                ['title' => 'Deployment Ready', 'type' => 'deployment'],
            ],
            'hotfix' => [
                ['title' => 'Issue Identified', 'type' => 'development'],
                ['title' => 'Fix Developed', 'type' => 'development'],
                ['title' => 'Quick Review', 'type' => 'development'],
                ['title' => 'Emergency Testing', 'type' => 'testing'],
                ['title' => 'Stakeholder Approval', 'type' => 'stakeholder'],
                ['title' => 'Deploy to Production', 'type' => 'deployment'],
            ],
        ];

        return $templates[$type] ?? $templates['feature'];
    }
}
