<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'output_id',
        'user_id',
        'type',
        'action',
        'signal_type',
        'confidence',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'confidence' => 'decimal:6'
    ];

    /**
     * Get the output that owns this feedback
     */
    public function output(): BelongsTo
    {
        return $this->belongsTo(Output::class);
    }

    /**
     * Get the user that provided this feedback
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for inline feedback
     */
    public function scopeInline($query)
    {
        return $query->where('type', 'inline');
    }

    /**
     * Scope for behavioral feedback
     */
    public function scopeBehavioral($query)
    {
        return $query->where('type', 'behavioral');
    }

    /**
     * Scope for explicit signals
     */
    public function scopeExplicit($query)
    {
        return $query->where('signal_type', 'explicit');
    }

    /**
     * Scope for passive signals
     */
    public function scopePassive($query)
    {
        return $query->where('signal_type', 'passive');
    }
}