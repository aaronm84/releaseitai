<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Input extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'type',
        'source',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    /**
     * Get the outputs for this input
     */
    public function outputs(): HasMany
    {
        return $this->hasMany(Output::class);
    }

    /**
     * Get the embedding for this input
     */
    public function embedding(): HasOne
    {
        return $this->hasOne(Embedding::class, 'content_id')->where('content_type', self::class);
    }
}