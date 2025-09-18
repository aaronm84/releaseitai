<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Communication extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'release_id',
        'initiated_by_user_id',
        'channel',
        'subject',
        'content',
        'metadata',
        'communication_type',
        'direction',
        'priority',
        'communication_date',
        'status',
        'outcome_summary',
        'follow_up_actions',
        'follow_up_due_date',
        'external_id',
        'thread_id',
        'attachments',
        'is_sensitive',
        'compliance_tags',
        'retention_policy',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'communication_date' => 'datetime',
            'follow_up_due_date' => 'datetime',
            'metadata' => 'array',
            'follow_up_actions' => 'array',
            'attachments' => 'array',
            'is_sensitive' => 'boolean',
        ];
    }

    /**
     * Valid communication channels
     */
    public const CHANNELS = ['email', 'slack', 'teams', 'meeting', 'phone', 'video_call', 'in_person', 'document', 'other'];

    /**
     * Valid communication types
     */
    public const TYPES = ['notification', 'discussion', 'approval_request', 'status_update', 'escalation', 'reminder', 'follow_up', 'decision', 'announcement'];

    /**
     * Valid directions
     */
    public const DIRECTIONS = ['inbound', 'outbound', 'internal'];

    /**
     * Valid priorities
     */
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /**
     * Valid statuses
     */
    public const STATUSES = ['sent', 'delivered', 'read', 'responded', 'failed', 'cancelled'];

    /**
     * Get the release associated with this communication.
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * Get the user who initiated this communication.
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /**
     * Get all participants for this communication.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(CommunicationParticipant::class);
    }

    /**
     * Get participants with specific type.
     */
    public function participantsByType(string $type): HasMany
    {
        return $this->participants()->where('participant_type', $type);
    }

    /**
     * Get all stakeholders involved in this communication.
     */
    public function stakeholders()
    {
        return User::whereIn('id', $this->participants()->pluck('user_id'))->get();
    }

    /**
     * Scope a query to communications for a specific release.
     */
    public function scopeForRelease(Builder $query, int $releaseId): void
    {
        $query->where('release_id', $releaseId);
    }

    /**
     * Scope a query to communications by channel.
     */
    public function scopeByChannel(Builder $query, string $channel): void
    {
        $query->where('channel', $channel);
    }

    /**
     * Scope a query to communications by type.
     */
    public function scopeByType(Builder $query, string $type): void
    {
        $query->where('communication_type', $type);
    }

    /**
     * Scope a query to communications by priority.
     */
    public function scopeByPriority(Builder $query, string $priority): void
    {
        $query->where('priority', $priority);
    }

    /**
     * Scope a query to communications in a date range.
     */
    public function scopeInDateRange(Builder $query, Carbon $startDate, Carbon $endDate): void
    {
        $query->whereBetween('communication_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to communications in a thread.
     */
    public function scopeInThread(Builder $query, string $threadId): void
    {
        $query->where('thread_id', $threadId);
    }

    /**
     * Scope a query to communications requiring follow-up.
     */
    public function scopeRequiringFollowUp(Builder $query): void
    {
        $query->whereNotNull('follow_up_due_date')
              ->where('follow_up_due_date', '>=', now())
              ->whereNotIn('status', ['responded', 'cancelled']);
    }

    /**
     * Scope a query to overdue follow-ups.
     */
    public function scopeOverdueFollowUp(Builder $query): void
    {
        $query->whereNotNull('follow_up_due_date')
              ->where('follow_up_due_date', '<', now())
              ->whereNotIn('status', ['responded', 'cancelled']);
    }

    /**
     * Scope a query to sensitive communications.
     */
    public function scopeSensitive(Builder $query): void
    {
        $query->where('is_sensitive', true);
    }

    /**
     * Add participants to this communication.
     */
    public function addParticipants(array $participants): void
    {
        foreach ($participants as $participant) {
            $this->participants()->create([
                'user_id' => $participant['user_id'],
                'participant_type' => $participant['type'] ?? 'to',
                'role' => $participant['role'] ?? null,
                'contact_method' => $participant['contact_method'] ?? null,
                'channel_metadata' => $participant['metadata'] ?? null,
            ]);
        }
    }

    /**
     * Mark communication as delivered.
     */
    public function markDelivered(): void
    {
        $this->update(['status' => 'delivered']);
    }

    /**
     * Mark communication as read.
     */
    public function markRead(): void
    {
        $this->update(['status' => 'read']);
    }

    /**
     * Mark communication as responded.
     */
    public function markResponded(): void
    {
        $this->update(['status' => 'responded']);
    }

    /**
     * Mark communication as failed.
     */
    public function markFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    /**
     * Update outcome summary.
     */
    public function updateOutcome(string $summary, array $followUpActions = []): void
    {
        $this->update([
            'outcome_summary' => $summary,
            'follow_up_actions' => $followUpActions,
        ]);
    }

    /**
     * Set follow-up due date.
     */
    public function setFollowUpDueDate(Carbon $date): void
    {
        $this->update(['follow_up_due_date' => $date]);
    }

    /**
     * Check if communication requires follow-up.
     */
    public function requiresFollowUp(): bool
    {
        return $this->follow_up_due_date !== null &&
               $this->follow_up_due_date->isFuture() &&
               !in_array($this->status, ['responded', 'cancelled']);
    }

    /**
     * Check if follow-up is overdue.
     */
    public function isFollowUpOverdue(): bool
    {
        return $this->follow_up_due_date !== null &&
               $this->follow_up_due_date->isPast() &&
               !in_array($this->status, ['responded', 'cancelled']);
    }

    /**
     * Get days until follow-up is due (negative if overdue).
     */
    public function getDaysUntilFollowUp(): ?int
    {
        if ($this->follow_up_due_date === null) {
            return null;
        }

        return now()->diffInDays($this->follow_up_due_date, false);
    }

    /**
     * Generate thread ID for grouping related communications.
     */
    public static function generateThreadId(): string
    {
        return 'thread_' . uniqid() . '_' . time();
    }

    /**
     * Get communications in the same thread.
     */
    public function getThreadCommunications()
    {
        if (!$this->thread_id) {
            return collect([$this]);
        }

        return self::where('thread_id', $this->thread_id)
                   ->orderBy('communication_date')
                   ->get();
    }

    /**
     * Create a communication log entry.
     */
    public static function logCommunication(array $data, array $participants = []): self
    {
        $communication = self::create([
            'release_id' => $data['release_id'],
            'initiated_by_user_id' => $data['initiated_by_user_id'],
            'channel' => $data['channel'],
            'subject' => $data['subject'] ?? null,
            'content' => $data['content'],
            'metadata' => $data['metadata'] ?? null,
            'communication_type' => $data['communication_type'],
            'direction' => $data['direction'],
            'priority' => $data['priority'] ?? 'medium',
            'communication_date' => $data['communication_date'] ?? now(),
            'status' => $data['status'] ?? 'sent',
            'external_id' => $data['external_id'] ?? null,
            'thread_id' => $data['thread_id'] ?? self::generateThreadId(),
            'attachments' => $data['attachments'] ?? null,
            'is_sensitive' => $data['is_sensitive'] ?? false,
            'compliance_tags' => $data['compliance_tags'] ?? null,
            'retention_policy' => $data['retention_policy'] ?? null,
        ]);

        if (!empty($participants)) {
            $communication->addParticipants($participants);
        }

        return $communication;
    }
}