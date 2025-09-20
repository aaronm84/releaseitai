<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Content extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'description',
        'content',
        'raw_content',
        'metadata',
        'file_path',
        'file_type',
        'file_size',
        'source_reference',
        'processed_at',
        'ai_summary',
        'status',
        'tags',
    ];

    protected $casts = [
        'metadata' => 'array',
        'tags' => 'array',
        'processed_at' => 'timestamp',
        'file_size' => 'integer',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stakeholders(): BelongsToMany
    {
        return $this->belongsToMany(Stakeholder::class, 'content_stakeholders')
                    ->withPivot('mention_type', 'confidence_score', 'context')
                    ->withTimestamps();
    }

    public function workstreams(): BelongsToMany
    {
        return $this->belongsToMany(Workstream::class, 'content_workstreams')
                    ->withPivot('relevance_type', 'confidence_score', 'context')
                    ->withTimestamps();
    }

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class, 'content_releases')
                    ->withPivot('relevance_type', 'confidence_score', 'context')
                    ->withTimestamps();
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ContentActionItem::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Helper methods
    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public function hasAiAnalysis(): bool
    {
        return !empty($this->ai_summary);
    }

    public function getAllRelatedStakeholders()
    {
        $directStakeholders = $this->stakeholders;
        $actionItemStakeholders = $this->actionItems()
            ->whereNotNull('assignee_stakeholder_id')
            ->with('assignee')
            ->get()
            ->pluck('assignee')
            ->filter();

        return $directStakeholders->merge($actionItemStakeholders)->unique('id');
    }

    public function markAsProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }
}
