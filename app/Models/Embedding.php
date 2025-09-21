<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Embedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_id',
        'content_type',
        'vector',
        'model',
        'dimensions',
        'normalized',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'normalized' => 'boolean'
    ];

    /**
     * Set the normalized attribute
     */
    public function setNormalizedAttribute($value)
    {
        $this->attributes['normalized'] = $value ? 'true' : 'false';
    }

    /**
     * Get the embeddable model (Input, Output, etc.)
     */
    public function content(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the vector as an array
     */
    public function getVectorAsArray(): array
    {
        return json_decode($this->vector, true) ?? [];
    }

    /**
     * Set the vector from an array
     */
    public function setVectorFromArray(array $vector): void
    {
        $this->vector = json_encode($vector);
    }

    /**
     * Calculate cosine similarity with another vector
     */
    public function cosineSimilarity(array $otherVector): float
    {
        $thisVector = $this->getVectorAsArray();

        if (count($thisVector) !== count($otherVector)) {
            throw new \InvalidArgumentException('Vectors must have the same dimensions');
        }

        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;

        for ($i = 0; $i < count($thisVector); $i++) {
            $dotProduct += $thisVector[$i] * $otherVector[$i];
            $magnitudeA += $thisVector[$i] * $thisVector[$i];
            $magnitudeB += $otherVector[$i] * $otherVector[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}