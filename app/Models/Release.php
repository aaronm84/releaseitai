<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Release extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'version',
        'description',
        'workstream_id',
        'target_date',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_date' => 'date',
        ];
    }

    /**
     * Get the workstream that owns the release.
     */
    public function workstream(): BelongsTo
    {
        return $this->belongsTo(Workstream::class);
    }

    /**
     * Get the stakeholder relationships for the release.
     */
    public function stakeholderReleases(): HasMany
    {
        return $this->hasMany(StakeholderRelease::class);
    }

    /**
     * Get the stakeholders for the release.
     */
    public function stakeholders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'stakeholder_releases')
            ->withPivot(['role', 'notification_preference'])
            ->withTimestamps()
            ->using(StakeholderRelease::class);
    }

    /**
     * Get stakeholders filtered by role.
     */
    public function stakeholdersByRole(string $role): BelongsToMany
    {
        return $this->stakeholders()->wherePivot('role', $role);
    }

    /**
     * Get the checklist item assignments for the release.
     */
    public function checklistItemAssignments(): HasMany
    {
        return $this->hasMany(ChecklistItemAssignment::class);
    }

    /**
     * Get the approval requests for the release.
     */
    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    /**
     * Get the communications for the release.
     */
    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }

    /**
     * Get the tasks for the release.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ReleaseTask::class);
    }

    /**
     * Get pending approval requests for the release.
     */
    public function pendingApprovalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class)->where('status', 'pending');
    }

    /**
     * Get overdue approval requests for the release.
     */
    public function overdueApprovalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class)->where('status', 'pending')->where('due_date', '<', now());
    }

    /**
     * Check if the release has all required approvals.
     */
    public function hasAllApprovalsApproved(): bool
    {
        $totalApprovals = $this->approvalRequests()->count();
        if ($totalApprovals === 0) {
            return true; // No approvals required
        }

        $approvedCount = $this->approvalRequests()->where('status', 'approved')->count();
        return $approvedCount === $totalApprovals;
    }

    /**
     * Check if the release has any rejected approvals.
     */
    public function hasRejectedApprovals(): bool
    {
        return $this->approvalRequests()->where('status', 'rejected')->exists();
    }

    /**
     * Check if the release has any pending approvals.
     */
    public function hasPendingApprovals(): bool
    {
        return $this->approvalRequests()->where('status', 'pending')->exists();
    }

    /**
     * Get the overall approval status for the release.
     */
    public function getApprovalStatus(): string
    {
        $totalApprovals = $this->approvalRequests()->count();

        if ($totalApprovals === 0) {
            return 'no_approvals_required';
        }

        $approvedCount = $this->approvalRequests()->where('status', 'approved')->count();
        $rejectedCount = $this->approvalRequests()->where('status', 'rejected')->count();

        if ($rejectedCount > 0) {
            return 'rejected';
        }

        if ($approvedCount === $totalApprovals) {
            return 'approved';
        }

        if ($approvedCount > 0) {
            return 'partially_approved';
        }

        return 'pending';
    }
}
