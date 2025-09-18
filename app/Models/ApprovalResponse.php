<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalResponse extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'approval_request_id',
        'responder_id',
        'decision',
        'comments',
        'conditions',
        'responded_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * Valid decisions
     */
    public const DECISIONS = ['approved', 'rejected', 'needs_changes'];

    /**
     * Get the approval request that owns this response.
     */
    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    /**
     * Get the user who responded to the approval request.
     */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responder_id');
    }

    /**
     * Check if the response has conditions.
     */
    public function hasConditions(): bool
    {
        return !empty($this->conditions);
    }

    /**
     * Get the conditions as a formatted list.
     */
    public function getFormattedConditions(): ?string
    {
        if (!$this->hasConditions()) {
            return null;
        }

        return '• ' . implode("\n• ", $this->conditions);
    }

    /**
     * Check if the decision is approved.
     */
    public function isApproved(): bool
    {
        return $this->decision === 'approved';
    }

    /**
     * Check if the decision is rejected.
     */
    public function isRejected(): bool
    {
        return $this->decision === 'rejected';
    }

    /**
     * Check if the decision requires changes.
     */
    public function needsChanges(): bool
    {
        return $this->decision === 'needs_changes';
    }
}
