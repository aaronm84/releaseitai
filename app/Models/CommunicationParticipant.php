<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationParticipant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'communication_id',
        'user_id',
        'participant_type',
        'role',
        'delivery_status',
        'delivered_at',
        'read_at',
        'responded_at',
        'response_content',
        'response_sentiment',
        'contact_method',
        'channel_metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'responded_at' => 'datetime',
            'channel_metadata' => 'array',
        ];
    }

    /**
     * Valid participant types
     */
    public const PARTICIPANT_TYPES = ['to', 'cc', 'bcc', 'attendee', 'optional_attendee', 'presenter', 'moderator'];

    /**
     * Valid roles
     */
    public const ROLES = ['sender', 'recipient', 'moderator', 'presenter', 'organizer', 'stakeholder', 'approver'];

    /**
     * Valid delivery statuses
     */
    public const DELIVERY_STATUSES = ['pending', 'delivered', 'read', 'responded', 'failed', 'bounced'];

    /**
     * Valid response sentiments
     */
    public const RESPONSE_SENTIMENTS = ['positive', 'negative', 'neutral'];

    /**
     * Get the communication that this participant belongs to.
     */
    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    /**
     * Get the user that this participant represents.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to participants by type.
     */
    public function scopeByType(Builder $query, string $type): void
    {
        $query->where('participant_type', $type);
    }

    /**
     * Scope a query to participants by role.
     */
    public function scopeByRole(Builder $query, string $role): void
    {
        $query->where('role', $role);
    }

    /**
     * Scope a query to participants by delivery status.
     */
    public function scopeByDeliveryStatus(Builder $query, string $status): void
    {
        $query->where('delivery_status', $status);
    }

    /**
     * Scope a query to participants who have responded.
     */
    public function scopeResponded(Builder $query): void
    {
        $query->whereNotNull('responded_at');
    }

    /**
     * Scope a query to participants who have not responded.
     */
    public function scopeNotResponded(Builder $query): void
    {
        $query->whereNull('responded_at');
    }

    /**
     * Scope a query to participants who have read the communication.
     */
    public function scopeRead(Builder $query): void
    {
        $query->whereNotNull('read_at');
    }

    /**
     * Scope a query to participants who have not read the communication.
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * Mark participant as delivered.
     */
    public function markDelivered(): void
    {
        $this->update([
            'delivery_status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark participant as read.
     */
    public function markRead(): void
    {
        $this->update([
            'delivery_status' => 'read',
            'read_at' => now(),
        ]);
    }

    /**
     * Mark participant as responded.
     */
    public function markResponded(string $responseContent = null, string $sentiment = null): void
    {
        $this->update([
            'delivery_status' => 'responded',
            'responded_at' => now(),
            'response_content' => $responseContent,
            'response_sentiment' => $sentiment,
        ]);
    }

    /**
     * Mark participant delivery as failed.
     */
    public function markFailed(): void
    {
        $this->update(['delivery_status' => 'failed']);
    }

    /**
     * Mark participant delivery as bounced.
     */
    public function markBounced(): void
    {
        $this->update(['delivery_status' => 'bounced']);
    }

    /**
     * Check if participant has responded.
     */
    public function hasResponded(): bool
    {
        return $this->responded_at !== null;
    }

    /**
     * Check if participant has read the communication.
     */
    public function hasRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Check if communication was delivered to participant.
     */
    public function wasDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    /**
     * Get the response time in hours (null if not responded).
     */
    public function getResponseTimeHours(): ?int
    {
        if (!$this->hasResponded() || !$this->wasDelivered()) {
            return null;
        }

        return $this->delivered_at->diffInHours($this->responded_at);
    }

    /**
     * Get the read time in hours (null if not read).
     */
    public function getReadTimeHours(): ?int
    {
        if (!$this->hasRead() || !$this->wasDelivered()) {
            return null;
        }

        return $this->delivered_at->diffInHours($this->read_at);
    }

    /**
     * Update channel metadata.
     */
    public function updateChannelMetadata(array $metadata): void
    {
        $currentMetadata = $this->channel_metadata ?? [];
        $this->update([
            'channel_metadata' => array_merge($currentMetadata, $metadata),
        ]);
    }
}