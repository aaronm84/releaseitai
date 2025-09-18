<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'workstream_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the workstream that owns this checklist template.
     */
    public function workstream(): BelongsTo
    {
        return $this->belongsTo(Workstream::class);
    }

    /**
     * Get the checklist items for this template.
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('order');
    }

    /**
     * Scope to get only active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
