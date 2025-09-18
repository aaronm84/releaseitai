<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkstreamPermission extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workstream_id',
        'user_id',
        'permission_type',
        'scope',
        'granted_by',
    ];

    /**
     * Get the workstream that this permission applies to.
     */
    public function workstream(): BelongsTo
    {
        return $this->belongsTo(Workstream::class);
    }

    /**
     * Get the user who has this permission.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who granted this permission.
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
