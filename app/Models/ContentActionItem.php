<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContentActionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_id',
        'action_text',
        'assignee_stakeholder_id',
        'priority',
        'due_date',
        'status',
        'confidence_score',
        'context',
    ];

    protected $casts = [
        'due_date' => 'date',
        'confidence_score' => 'decimal:2',
    ];

    // Relationships
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Stakeholder::class, 'assignee_stakeholder_id');
    }

    public function stakeholders(): BelongsToMany
    {
        return $this->belongsToMany(Stakeholder::class, 'action_item_stakeholders')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function workstreams(): BelongsToMany
    {
        return $this->belongsToMany(Workstream::class, 'action_item_workstreams')
                    ->withTimestamps();
    }

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class, 'action_item_releases')
                    ->withTimestamps();
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopePriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeAssignedTo($query, int $stakeholderId)
    {
        return $query->where('assignee_stakeholder_id', $stakeholderId);
    }

    public function scopeDueBy($query, string $date)
    {
        return $query->where('due_date', '<=', $date);
    }

    public function scopeWithoutDueDate($query)
    {
        return $query->whereNull('due_date');
    }

    public function scopeHighConfidence($query, float $threshold = 0.7)
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    public function scopeLowConfidence($query, float $threshold = 0.7)
    {
        return $query->where('confidence_score', '<', $threshold);
    }

    // Helper methods
    public function isOverdue(): bool
    {
        if (!$this->due_date || $this->status === 'completed' || $this->status === 'cancelled') {
            return false;
        }

        return $this->due_date->isPast();
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    public function markAsInProgress(): void
    {
        $this->update(['status' => 'in_progress']);
    }

    public function getAllRelatedEntities(): array
    {
        return [
            'stakeholders' => $this->stakeholders,
            'workstreams' => $this->workstreams,
            'releases' => $this->releases,
        ];
    }
}
