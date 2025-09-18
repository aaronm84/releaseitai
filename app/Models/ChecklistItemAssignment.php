<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistItemAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_item_id',
        'assignee_id',
        'release_id',
        'due_date',
        'priority',
        'status',
        'assigned_at',
        'started_at',
        'completed_at',
        'notes',
        'sla_deadline',
        'escalated',
        'escalated_at',
        'escalation_reason',
        'reassigned',
        'reassignment_reason',
        'previous_assignee_id',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'sla_deadline' => 'datetime',
        'escalated_at' => 'datetime',
        'escalated' => 'boolean',
        'reassigned' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($assignment) {
            $assignment->assigned_at = $assignment->assigned_at ?: now();
        });

        static::created(function ($assignment) {
            // Calculate SLA deadline after the record is created and relationships can be loaded
            $assignment->load('checklistItem');
            $assignment->calculateSlaDeadline();
            $assignment->saveQuietly(); // Save without triggering events again
        });

        static::updating(function ($assignment) {
            if ($assignment->isDirty('assignee_id') && $assignment->getOriginal('assignee_id')) {
                $assignment->reassigned = true;
                $assignment->previous_assignee_id = $assignment->getOriginal('assignee_id');
            }
        });
    }

    /**
     * Get the checklist item for this assignment.
     */
    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class);
    }

    /**
     * Get the assignee for this assignment.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get the previous assignee for this assignment.
     */
    public function previousAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_assignee_id');
    }

    /**
     * Get the release for this assignment.
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * Get dependencies where this assignment is the prerequisite.
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(ChecklistItemDependency::class, 'prerequisite_assignment_id');
    }

    /**
     * Get dependencies where this assignment is the dependent.
     */
    public function prerequisites(): HasMany
    {
        return $this->hasMany(ChecklistItemDependency::class, 'dependent_assignment_id');
    }

    /**
     * Calculate and set the SLA deadline based on the checklist item's SLA hours.
     */
    public function calculateSlaDeadline(): void
    {
        if ($this->checklistItem && $this->checklistItem->sla_hours && $this->assigned_at) {
            $this->sla_deadline = Carbon::parse($this->assigned_at)->addHours($this->checklistItem->sla_hours);
        }
    }

    /**
     * Get the hours until SLA breach.
     */
    public function getHoursUntilSlaBreachAttribute(): ?float
    {
        if (!$this->sla_deadline) {
            return null;
        }

        if ($this->status === 'completed') {
            return null;
        }

        $now = now();
        $deadline = Carbon::parse($this->sla_deadline);

        return $deadline->diffInRealHours($now, false); // false = negative if past deadline
    }

    /**
     * Check if the SLA has been breached.
     */
    public function getIsSlaBreachedAttribute(): bool
    {
        if (!$this->sla_deadline || $this->status === 'completed') {
            return false;
        }

        return now()->greaterThan($this->sla_deadline);
    }

    /**
     * Get the SLA status.
     */
    public function getSlaStatusAttribute(): string
    {
        if (!$this->sla_deadline) {
            return 'no_sla';
        }

        if ($this->status === 'completed') {
            return 'completed';
        }

        $hoursUntilBreach = $this->hours_until_sla_breach;

        if ($hoursUntilBreach < 0) {
            return 'breached';
        } elseif ($hoursUntilBreach <= 24) {
            return 'at_risk';
        } else {
            return 'on_track';
        }
    }

    /**
     * Check if the assignment is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'completed') {
            return false;
        }

        return now()->greaterThan($this->due_date);
    }

    /**
     * Check if the assignment is at risk (due within 24 hours).
     */
    public function getIsAtRiskAttribute(): bool
    {
        if ($this->status === 'completed' || $this->is_overdue) {
            return false;
        }

        return now()->diffInHours($this->due_date) <= 24;
    }

    /**
     * Mark the assignment as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the assignment as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Escalate the assignment.
     */
    public function escalate(string $reason): void
    {
        $this->update([
            'escalated' => true,
            'escalated_at' => now(),
            'escalation_reason' => $reason,
        ]);
    }

    /**
     * Reassign to a different user.
     */
    public function reassignTo(int $newAssigneeId, string $reason): void
    {
        $this->update([
            'assignee_id' => $newAssigneeId,
            'reassignment_reason' => $reason,
        ]);
    }

    /**
     * Scope for pending assignments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for in-progress assignments.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope for completed assignments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for overdue assignments.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', 'completed')
                    ->where('due_date', '<', now());
    }

    /**
     * Scope for at-risk assignments (due within 24 hours).
     */
    public function scopeAtRisk($query)
    {
        return $query->where('status', '!=', 'completed')
                    ->where('due_date', '>', now())
                    ->where('due_date', '<=', now()->addHours(24));
    }

    /**
     * Scope for assignments by assignee.
     */
    public function scopeForAssignee($query, int $assigneeId)
    {
        return $query->where('assignee_id', $assigneeId);
    }

    /**
     * Scope for assignments by release.
     */
    public function scopeForRelease($query, int $releaseId)
    {
        return $query->where('release_id', $releaseId);
    }

    /**
     * Scope for escalated assignments.
     */
    public function scopeEscalated($query)
    {
        return $query->where('escalated', true);
    }

    /**
     * Scope for SLA breached assignments.
     */
    public function scopeSlaBreached($query)
    {
        return $query->where('status', '!=', 'completed')
                    ->whereNotNull('sla_deadline')
                    ->where('sla_deadline', '<', now());
    }
}
