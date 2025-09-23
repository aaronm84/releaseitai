<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Stakeholder;
use App\Models\Communication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StakeholderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Stakeholder::class, 'stakeholder');
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        // Get stakeholders for the current user with filtering and searching
        $query = Stakeholder::query()
            ->orderBy('last_contact_at', 'desc')
            ->orderBy('name');

        // Apply search filter
        if ($request->search) {
            $query->search($request->search);
        }

        // Apply influence level filter
        if ($request->influence_level) {
            $query->byInfluence($request->influence_level);
        }

        // Apply support level filter
        if ($request->support_level) {
            $query->bySupport($request->support_level);
        }

        // Apply follow-up filter
        if ($request->needs_follow_up) {
            $query->needsFollowUp();
        }

        $stakeholders = $query->get()
            ->map(function ($stakeholder) {
                return [
                    'id' => $stakeholder->id,
                    'name' => $stakeholder->name,
                    'email' => $stakeholder->email,
                    'title' => $stakeholder->title,
                    'company' => $stakeholder->company,
                    'department' => $stakeholder->department,
                    'phone' => $stakeholder->phone,
                    'linkedin_handle' => $stakeholder->linkedin_handle,
                    'twitter_handle' => $stakeholder->twitter_handle,
                    'slack_handle' => $stakeholder->slack_handle,
                    'teams_handle' => $stakeholder->teams_handle,
                    'preferred_communication_channel' => $stakeholder->preferred_communication_channel,
                    'communication_frequency' => $stakeholder->communication_frequency,
                    'tags' => $stakeholder->tags ?? [],
                    'notes' => $stakeholder->notes,
                    'stakeholder_notes' => $stakeholder->stakeholder_notes,
                    'last_contact_at' => $stakeholder->last_contact_at,
                    'last_contact_channel' => $stakeholder->last_contact_channel,
                    'influence_level' => $stakeholder->influence_level,
                    'support_level' => $stakeholder->support_level,
                    'timezone' => $stakeholder->timezone,
                    'is_available' => $stakeholder->is_available,
                    'needs_follow_up' => $stakeholder->needs_follow_up,
                    'unavailable_until' => $stakeholder->unavailable_until,
                    'days_since_contact' => $stakeholder->days_since_contact,
                ];
            });


        // Calculate summary metrics
        $totalStakeholders = $stakeholders->count();
        $needsFollowUp = $stakeholders->where('needs_follow_up', true)->count();
        $byInfluence = $stakeholders->groupBy('influence_level')->map->count();
        $bySupport = $stakeholders->groupBy('support_level')->map->count();

        return Inertia::render('Stakeholders/Index', [
            'stakeholders' => $stakeholders->values(),
            'metrics' => [
                'total_stakeholders' => $totalStakeholders,
                'needs_follow_up' => $needsFollowUp,
                'by_influence' => $byInfluence,
                'by_support' => $bySupport,
            ],
            'filters' => [
                'search' => $request->search,
                'influence_level' => $request->influence_level,
                'support_level' => $request->support_level,
                'needs_follow_up' => $request->needs_follow_up,
            ],
            'user' => $user,
        ]);
    }

    public function show(Stakeholder $stakeholder)
    {
        $user = Auth::user();

        // Get recent communications with this stakeholder
        $recentCommunications = Communication::whereHas('participants', function ($query) use ($stakeholder) {
                $query->where('user_id', $stakeholder->id);
            })
            ->with(['participants' => function ($query) use ($stakeholder) {
                $query->where('user_id', $stakeholder->id);
            }])
            ->orderBy('communication_date', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($communication) {
                return [
                    'id' => $communication->id,
                    'subject' => $communication->subject,
                    'content' => $communication->content,
                    'channel' => $communication->channel,
                    'direction' => $communication->direction,
                    'communication_date' => $communication->communication_date,
                    'priority' => $communication->priority,
                    'communication_type' => $communication->communication_type,
                ];
            });

        // Get releases where they're a stakeholder
        $stakeholderReleases = $stakeholder->releases()
            ->with('workstream:id,name')
            ->select(['releases.id', 'releases.name', 'releases.status', 'releases.target_date', 'releases.workstream_id'])
            ->get()
            ->map(function ($release) use ($stakeholder) {
                return [
                    'id' => $release->id,
                    'name' => $release->name,
                    'status' => $release->status,
                    'target_date' => $release->target_date,
                    'workstream_name' => $release->workstream?->name,
                    'role' => $release->pivot->role,
                    'notification_preference' => $release->pivot->notification_preference,
                ];
            });

        return Inertia::render('Stakeholders/Show', [
            'stakeholder' => [
                'id' => $stakeholder->id,
                'name' => $stakeholder->name,
                'email' => $stakeholder->email,
                'title' => $stakeholder->title,
                'company' => $stakeholder->company,
                'department' => $stakeholder->department,
                'phone' => $stakeholder->phone,
                'slack_handle' => $stakeholder->slack_handle,
                'teams_handle' => $stakeholder->teams_handle,
                'preferred_communication_channel' => $stakeholder->preferred_communication_channel,
                'communication_frequency' => $stakeholder->communication_frequency,
                'tags' => $stakeholder->tags ?? [],
                'stakeholder_notes' => $stakeholder->stakeholder_notes,
                'last_contact_at' => $stakeholder->last_contact_at,
                'last_contact_channel' => $stakeholder->last_contact_channel,
                'influence_level' => $stakeholder->influence_level,
                'support_level' => $stakeholder->support_level,
                'timezone' => $stakeholder->timezone,
                'is_available' => $stakeholder->is_available,
                'unavailable_until' => $stakeholder->unavailable_until,
            ],
            'recentCommunications' => $recentCommunications,
            'stakeholderReleases' => $stakeholderReleases,
            'user' => $user,
        ]);
    }

    public function update(Stakeholder $stakeholder, Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'title' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'linkedin_handle' => 'nullable|string|max:255',
            'twitter_handle' => 'nullable|string|max:255',
            'slack_handle' => 'nullable|string|max:255',
            'teams_handle' => 'nullable|string|max:255',
            'preferred_communication_channel' => 'nullable|in:email,slack,teams,phone,linkedin,twitter',
            'communication_frequency' => 'nullable|in:immediate,daily,weekly,monthly,quarterly,as_needed',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
            'stakeholder_notes' => 'nullable|string',
            'influence_level' => 'nullable|in:low,medium,high',
            'support_level' => 'nullable|in:low,medium,high',
            'timezone' => 'nullable|string|max:255',
            'is_available' => 'nullable|boolean',
            'needs_follow_up' => 'nullable|boolean',
            'unavailable_until' => 'nullable|date',
        ]);

        $stakeholder->update($request->only([
            'name', 'email', 'title', 'company', 'department', 'phone',
            'linkedin_handle', 'twitter_handle', 'slack_handle', 'teams_handle',
            'preferred_communication_channel', 'communication_frequency', 'tags',
            'notes', 'stakeholder_notes', 'influence_level', 'support_level',
            'timezone', 'is_available', 'needs_follow_up', 'unavailable_until'
        ]));

        return redirect()->route('stakeholders.show', $stakeholder)->with('success', 'Stakeholder updated successfully.');
    }

    public function edit(Stakeholder $stakeholder)
    {
        return Inertia::render('Stakeholders/Edit', [
            'stakeholder' => [
                'id' => $stakeholder->id,
                'name' => $stakeholder->name,
                'email' => $stakeholder->email,
                'title' => $stakeholder->title,
                'company' => $stakeholder->company,
                'department' => $stakeholder->department,
                'phone' => $stakeholder->phone,
                'linkedin_handle' => $stakeholder->linkedin_handle,
                'twitter_handle' => $stakeholder->twitter_handle,
                'slack_handle' => $stakeholder->slack_handle,
                'teams_handle' => $stakeholder->teams_handle,
                'preferred_communication_channel' => $stakeholder->preferred_communication_channel,
                'communication_frequency' => $stakeholder->communication_frequency,
                'tags' => $stakeholder->tags ?? [],
                'notes' => $stakeholder->notes,
                'stakeholder_notes' => $stakeholder->stakeholder_notes,
                'influence_level' => $stakeholder->influence_level,
                'support_level' => $stakeholder->support_level,
                'timezone' => $stakeholder->timezone,
                'is_available' => $stakeholder->is_available,
                'needs_follow_up' => $stakeholder->needs_follow_up,
                'unavailable_until' => $stakeholder->unavailable_until,
            ]
        ]);
    }

    public function updateLastContact(Stakeholder $stakeholder, Request $request)
    {
        $request->validate([
            'channel' => 'required|in:email,slack,teams,phone,linkedin,twitter,in_person,other',
            'contact_date' => 'nullable|date',
        ]);

        $stakeholder->update([
            'last_contact_at' => $request->contact_date ? \Carbon\Carbon::parse($request->contact_date) : now(),
            'last_contact_channel' => $request->channel,
        ]);

        return redirect()->back()->with('success', 'Last contact updated successfully.');
    }

    public function create()
    {
        return Inertia::render('Stakeholders/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'title' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'linkedin_handle' => 'nullable|string|max:255',
            'twitter_handle' => 'nullable|string|max:255',
            'slack_handle' => 'nullable|string|max:255',
            'teams_handle' => 'nullable|string|max:255',
            'preferred_communication_channel' => 'nullable|in:email,slack,teams,phone,linkedin,twitter',
            'communication_frequency' => 'nullable|in:immediate,daily,weekly,monthly,quarterly,as_needed',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
            'stakeholder_notes' => 'nullable|string',
            'influence_level' => 'nullable|in:low,medium,high',
            'support_level' => 'nullable|in:low,medium,high',
            'timezone' => 'nullable|string|max:255',
            'is_available' => 'nullable|boolean',
            'needs_follow_up' => 'nullable|boolean',
        ]);

        $stakeholder = Stakeholder::create($request->only([
            'name', 'email', 'title', 'company', 'department', 'phone',
            'linkedin_handle', 'twitter_handle', 'slack_handle', 'teams_handle',
            'preferred_communication_channel', 'communication_frequency', 'tags',
            'notes', 'stakeholder_notes', 'influence_level', 'support_level',
            'timezone', 'is_available', 'needs_follow_up'
        ]));

        return redirect()->route('stakeholders.index')->with('success', 'Stakeholder created successfully.');
    }

    public function destroy(Stakeholder $stakeholder)
    {
        $stakeholder->delete();

        return redirect()->route('stakeholders.index')->with('success', 'Stakeholder deleted successfully.');
    }
}