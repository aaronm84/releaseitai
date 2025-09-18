<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class StakeholderRelease extends Pivot
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'stakeholder_releases';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'release_id',
        'role',
        'notification_preference',
    ];

    /**
     * The valid stakeholder roles.
     */
    public const ROLES = ['owner', 'reviewer', 'approver', 'observer'];

    /**
     * The valid notification preferences.
     */
    public const NOTIFICATION_PREFERENCES = ['email', 'slack', 'none'];

    /**
     * Get the user (stakeholder) for this relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the release for this relationship.
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * Scope a query to only include stakeholders with a specific role.
     */
    public function scopeOfRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope a query to only include stakeholders with a specific notification preference.
     */
    public function scopeWithNotificationPreference($query, string $preference)
    {
        return $query->where('notification_preference', $preference);
    }
}
