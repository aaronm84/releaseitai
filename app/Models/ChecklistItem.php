<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_template_id',
        'title',
        'description',
        'order',
        'estimated_hours',
        'sla_hours',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the checklist template that owns this item.
     */
    public function checklistTemplate(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class);
    }

    /**
     * Get the assignments for this checklist item.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ChecklistItemAssignment::class);
    }

    /**
     * Scope to get only active items.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only required items.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope to order by the order field.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
