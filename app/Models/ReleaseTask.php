<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'release_id',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'assigned_to',
        'due_date',
        'order',
        'is_blocker',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_blocker' => 'boolean',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
