<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiJob extends Model
{
    protected $fillable = [
        'provider',
        'method',
        'prompt_hash',
        'prompt_length',
        'options',
        'status',
        'tokens_used',
        'cost',
        'response_length',
        'error_message',
        'completed_at',
        'user_id',
    ];

    protected $casts = [
        'options' => 'array',
        'cost' => 'decimal:6',
        'completed_at' => 'timestamp',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for successful jobs
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed jobs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for processing jobs
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope for jobs by provider
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Get processing time in seconds
     */
    public function getProcessingTimeAttribute(): ?int
    {
        if (!$this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->created_at);
    }

    /**
     * Check if job was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if job failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if job is still processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }
}
