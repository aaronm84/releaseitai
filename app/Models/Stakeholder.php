<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Stakeholder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'company',
        'title',
        'department',
        'phone',
        'linkedin_handle',
        'twitter_handle',
        'slack_handle',
        'teams_handle',
        'preferred_communication_channel',
        'communication_frequency',
        'tags',
        'influence_level',
        'support_level',
        'notes',
        'stakeholder_notes',
        'is_available',
        'needs_follow_up',
        'timezone',
        'unavailable_until',
        'last_contact_at',
        'last_contact_channel',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_available' => 'boolean',
        'needs_follow_up' => 'boolean',
        'last_contact_at' => 'datetime',
        'unavailable_until' => 'datetime',
    ];

    public function setIsAvailableAttribute($value)
    {
        $this->attributes['is_available'] = $value ? 'true' : 'false';
    }

    public function setNeedsFollowUpAttribute($value)
    {
        $this->attributes['needs_follow_up'] = $value ? 'true' : 'false';
    }

    protected static function booted()
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (Auth::check()) {
                $query->where('user_id', Auth::id());
            }
        });

        static::creating(function (Stakeholder $stakeholder) {
            if (Auth::check() && !$stakeholder->user_id) {
                $stakeholder->user_id = Auth::id();
            }

            // Validate required fields
            $stakeholder->validateModel();
        });

        static::updating(function (Stakeholder $stakeholder) {
            // Validate required fields
            $stakeholder->validateModel();
        });
    }

    protected function validateModel()
    {
        $userId = $this->user_id ?: Auth::id();
        $stakeholderId = $this->id ?: 'NULL';

        $rules = [
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'email',
                "unique:stakeholders,email,{$stakeholderId},id,user_id,{$userId}"
            ],
            'user_id' => 'required|exists:users,id',
            'influence_level' => 'nullable|in:low,medium,high',
            'support_level' => 'nullable|in:low,medium,high',
            'preferred_communication_channel' => 'nullable|in:email,slack,teams,phone,linkedin,twitter',
            'communication_frequency' => 'nullable|in:immediate,daily,weekly,monthly,quarterly,as_needed',
            'last_contact_channel' => 'nullable|in:email,slack,teams,phone,linkedin,twitter,in_person,other',
        ];

        $validator = Validator::make($this->getAttributes(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class, 'stakeholder_releases')
            ->withPivot(['role', 'notification_preference'])
            ->withTimestamps();
    }

    public function scopeByInfluence(Builder $query, string $level): Builder
    {
        return $query->where('influence_level', $level);
    }

    public function scopeBySupport(Builder $query, string $level): Builder
    {
        return $query->where('support_level', $level);
    }

    public function scopeNeedsFollowUp(Builder $query): Builder
    {
        return $query->where('needs_follow_up', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
              ->orWhere('email', 'ilike', "%{$search}%")
              ->orWhere('company', 'ilike', "%{$search}%");
        });
    }

    public function scopeForCurrentUser(Builder $query): Builder
    {
        return $query->where('user_id', Auth::id());
    }

    public function scopeWithoutGlobalScope(Builder $query, string $scope): Builder
    {
        return $query->withoutGlobalScope($scope);
    }

    public function getDaysSinceContactAttribute(): ?int
    {
        if (!$this->last_contact_at) {
            return null;
        }

        return $this->last_contact_at->diffInDays(now());
    }

    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', $this->name))
            ->map(fn($name) => strtoupper(substr($name, 0, 1)))
            ->implode('');
    }

    public function getNeedsFollowUpAttribute(): bool
    {
        // If already explicitly set in database, use that value
        $explicitValue = $this->getRawOriginal('needs_follow_up');
        if ($explicitValue !== null) {
            return (bool) $explicitValue;
        }

        // Auto-calculate based on last contact
        if (!$this->last_contact_at) {
            return false;
        }

        // Need follow-up if no contact in the last 10 days
        return $this->last_contact_at->diffInDays(now()) > 10;
    }
}
