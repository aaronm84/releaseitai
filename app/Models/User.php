<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'title',
        'company',
        'department',
        'phone',
        'slack_handle',
        'teams_handle',
        'preferred_communication_channel',
        'communication_frequency',
        'tags',
        'stakeholder_notes',
        'last_contact_at',
        'last_contact_channel',
        'influence_level',
        'support_level',
        'timezone',
        'is_available',
        'unavailable_until',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'tags' => 'array',
            'last_contact_at' => 'datetime',
            'is_available' => 'boolean',
            'unavailable_until' => 'date',
        ];
    }

    /**
     * Get the workstreams owned by the user.
     */
    public function ownedWorkstreams(): HasMany
    {
        return $this->hasMany(Workstream::class, 'owner_id');
    }

    /**
     * Get the stakeholder release pivot records for the user.
     */
    public function stakeholderReleasePivots(): HasMany
    {
        return $this->hasMany(StakeholderRelease::class);
    }

    /**
     * Get the releases where the user is a stakeholder.
     */
    public function stakeholderReleases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class, 'stakeholder_releases')
            ->withPivot(['role', 'notification_preference'])
            ->withTimestamps()
            ->using(StakeholderRelease::class);
    }

    /**
     * Get releases where the user is a stakeholder with a specific role.
     */
    public function releasesByRole(string $role): BelongsToMany
    {
        return $this->stakeholderReleases()->wherePivot('role', $role);
    }

    /**
     * Get approval requests where the user is the approver.
     */
    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'approver_id');
    }

    /**
     * Get pending approval requests for the user.
     */
    public function pendingApprovalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'approver_id')->where('status', 'pending');
    }

    /**
     * Get overdue approval requests for the user.
     */
    public function overdueApprovalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'approver_id')
            ->where('status', 'pending')
            ->where('due_date', '<', now());
    }

    /**
     * Get approval responses made by the user.
     */
    public function approvalResponses(): HasMany
    {
        return $this->hasMany(ApprovalResponse::class, 'responder_id');
    }

    /**
     * Get approval requests that are due soon for the user.
     */
    public function approvalRequestsDueSoon(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'approver_id')
            ->where('status', 'pending')
            ->where('due_date', '<=', now()->addDay())
            ->where('due_date', '>=', now());
    }

    /**
     * Get approval requests by type for the user.
     */
    public function approvalRequestsByType(string $type): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'approver_id')
            ->where('approval_type', $type);
    }

    /**
     * Get approval requests by priority for the user.
     */
    public function approvalRequestsByPriority(string $priority): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'approver_id')
            ->where('priority', $priority);
    }

    /**
     * Get the count of pending approvals for the user.
     */
    public function getPendingApprovalsCount(): int
    {
        return $this->pendingApprovalRequests()->count();
    }

    /**
     * Get the count of overdue approvals for the user.
     */
    public function getOverdueApprovalsCount(): int
    {
        return $this->overdueApprovalRequests()->count();
    }

    /**
     * Check if the user has any overdue approvals.
     */
    public function hasOverdueApprovals(): bool
    {
        return $this->overdueApprovalRequests()->exists();
    }

    /**
     * Check if the user has any approvals due soon.
     */
    public function hasApprovalsDueSoon(): bool
    {
        return $this->approvalRequestsDueSoon()->exists();
    }
}
