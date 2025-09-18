<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApprovalRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'release_id',
        'approval_type',
        'approver_id',
        'description',
        'due_date',
        'priority',
        'status',
        'cancellation_reason',
        'reminder_count',
        'last_reminder_sent',
        'auto_expire_days',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'last_reminder_sent' => 'datetime',
        ];
    }

    /**
     * Valid approval types
     */
    public const APPROVAL_TYPES = ['legal', 'security', 'design', 'technical'];

    /**
     * Valid priorities
     */
    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    /**
     * Valid statuses
     */
    public const STATUSES = ['pending', 'approved', 'rejected', 'needs_changes', 'cancelled', 'expired'];

    /**
     * Get the release that owns the approval request.
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * Get the user who is the approver.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Get the approval response for this request.
     */
    public function response(): HasOne
    {
        return $this->hasOne(ApprovalResponse::class);
    }

    /**
     * Scope a query to only include pending approvals.
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include overdue approvals.
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', 'pending')
              ->where('due_date', '<', now());
    }

    /**
     * Scope a query to only include approvals due soon (within 24 hours).
     */
    public function scopeDueSoon(Builder $query): void
    {
        $query->where('status', 'pending')
              ->where('due_date', '<=', now()->addDay())
              ->where('due_date', '>=', now());
    }

    /**
     * Scope a query to only include expired approvals (past auto-expire date).
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('status', 'pending')
              ->whereRaw('due_date + INTERVAL \'1 DAY\' * auto_expire_days < NOW()');
    }

    /**
     * Check if the approval request is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date->isPast();
    }

    /**
     * Check if the approval request is due soon (within 24 hours).
     */
    public function isDueSoon(): bool
    {
        return $this->status === 'pending' &&
               $this->due_date->isFuture() &&
               $this->due_date->lessThanOrEqualTo(now()->addDay());
    }

    /**
     * Check if the approval request has expired based on auto-expire days.
     */
    public function hasExpired(): bool
    {
        return $this->status === 'pending' &&
               $this->due_date->addDays($this->auto_expire_days)->isPast();
    }

    /**
     * Get the number of days until due (negative if overdue).
     */
    public function getDaysUntilDue(): int
    {
        return now()->diffInDays($this->due_date, false);
    }

    /**
     * Get the number of days overdue (0 if not overdue).
     */
    public function getDaysOverdue(): int
    {
        return $this->isOverdue() ? now()->diffInDays($this->due_date) : 0;
    }

    /**
     * Mark the approval request as approved and create response.
     */
    public function approve(User $responder, string $comments = null, array $conditions = []): ApprovalResponse
    {
        $this->update(['status' => 'approved']);

        return $this->response()->create([
            'responder_id' => $responder->id,
            'decision' => 'approved',
            'comments' => $comments,
            'conditions' => empty($conditions) ? null : $conditions,
        ]);
    }

    /**
     * Mark the approval request as rejected and create response.
     */
    public function reject(User $responder, string $comments = null): ApprovalResponse
    {
        $this->update(['status' => 'rejected']);

        return $this->response()->create([
            'responder_id' => $responder->id,
            'decision' => 'rejected',
            'comments' => $comments,
        ]);
    }

    /**
     * Mark the approval request as needing changes and create response.
     */
    public function needsChanges(User $responder, string $comments = null): ApprovalResponse
    {
        $this->update(['status' => 'needs_changes']);

        return $this->response()->create([
            'responder_id' => $responder->id,
            'decision' => 'needs_changes',
            'comments' => $comments,
        ]);
    }

    /**
     * Cancel the approval request.
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Mark the approval request as expired.
     */
    public function expire(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Increment reminder count and update last reminder sent timestamp.
     */
    public function recordReminderSent(): void
    {
        $this->update([
            'reminder_count' => $this->reminder_count + 1,
            'last_reminder_sent' => now(),
        ]);
    }

    /**
     * Check if the user can respond to this approval request.
     */
    public function canRespond(User $user): bool
    {
        return $this->approver_id === $user->id && $this->status === 'pending';
    }
}
