<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Output extends Model
{
    use HasFactory;

    protected $fillable = [
        'input_id',
        'content',
        'type',
        'ai_model',
        'quality_score',
        'version',
        'parent_output_id',
        'feedback_integrated',
        'feedback_count',
        'content_format',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'feedback_integrated' => 'boolean',
        'quality_score' => 'decimal:6'
    ];

    protected $attributes = [
        'feedback_count' => 0,
        'version' => 1,
        'content_format' => 'json'
    ];

    /**
     * Set the feedback_integrated attribute
     */
    public function setFeedbackIntegratedAttribute($value)
    {
        $this->attributes['feedback_integrated'] = $value ? 'true' : 'false';
    }

    /**
     * Get the input that owns this output
     */
    public function input(): BelongsTo
    {
        return $this->belongsTo(Input::class);
    }

    /**
     * Get the parent output if this is a version
     */
    public function parentOutput(): BelongsTo
    {
        return $this->belongsTo(Output::class, 'parent_output_id');
    }

    /**
     * Get child versions of this output
     */
    public function versions(): HasMany
    {
        return $this->hasMany(Output::class, 'parent_output_id');
    }

    /**
     * Get feedback for this output
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Get the embedding for this output
     */
    public function embedding(): HasOne
    {
        return $this->hasOne(Embedding::class, 'content_id')->where('content_type', self::class);
    }
}