<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * Implements MustVerifyEmail so registering queues a verification mail
 * through Laravel's Registered listener (FR-AUTH-01). No route requires
 * verification yet — the closed-group product has no reason to lock a
 * member out while they wait for mail.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /**
     * `Notifiable` routes the `database` and `broadcast` channels — the
     * latter targets this user's private `App.Models.User.{id}` Pusher
     * channel, authorized in routes/channels.php. `HasPushSubscriptions`
     * routes the `webpush` channel.
     *
     * @use HasFactory<UserFactory>
     */
    use HasApiTokens, HasFactory, HasPushSubscriptions, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * `xp` and `level` are deliberately absent: they are gamification
     * counters owned by the recalculation job, not user input (02 §5).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_path',
        'timezone',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
            'is_admin' => 'boolean',
            'disabled_at' => 'datetime',
            'xp' => 'integer',
            'level' => 'integer',
        ];
    }

    /**
     * @return HasMany<Goal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<Sprint, $this>
     */
    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * The single running-or-paused sprint, if any (FR-SPR-08). Used by
     * StartSprintAction to reject a second one.
     *
     * @return HasOne<Sprint, $this>
     */
    public function activeSprint(): HasOne
    {
        return $this->hasOne(Sprint::class)->active()->latestOfMany();
    }

    /**
     * @return HasMany<GroupMember, $this>
     */
    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function ownedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    /**
     * @return HasOne<Streak, $this>
     */
    public function streak(): HasOne
    {
        return $this->hasOne(Streak::class);
    }

    /**
     * @return BelongsToMany<Badge, $this>
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withPivot('awarded_at')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Mentorship, $this>
     */
    public function mentorshipsAsMentor(): HasMany
    {
        return $this->hasMany(Mentorship::class, 'mentor_id');
    }

    /**
     * @return HasMany<Mentorship, $this>
     */
    public function mentorshipsAsMentee(): HasMany
    {
        return $this->hasMany(Mentorship::class, 'mentee_id');
    }

    public function belongsToGroup(int $groupId): bool
    {
        return $this->groupMemberships()->where('group_id', $groupId)->exists();
    }

    /**
     * FR-MENT-01: there is no public directory, so "any user" in practice
     * means "any user I already know through a shared Group". This is the
     * single question that gate asks, and it is asked inside
     * RequestMentorshipAction as a real authorization rule.
     */
    public function sharesGroupWith(User $other): bool
    {
        if ($this->id === $other->id) {
            return false;
        }

        return GroupMember::query()
            ->where('user_id', $other->id)
            ->whereIn('group_id', GroupMember::query()
                ->where('user_id', $this->id)
                ->select('group_id'))
            ->exists();
    }

    /**
     * A subquery of this member's group ids, for use inside visibility
     * scopes — cheaper and more honest than pulling the ids into PHP only to
     * send them back as a whereIn list.
     *
     * @return Builder<GroupMember>
     */
    public function groupIdsQuery()
    {
        return GroupMember::query()->where('user_id', $this->id)->select('group_id');
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    /**
     * Gamification is opt-in per FR-GAM-02; an absent setting means enabled.
     */
    public function hasGamificationEnabled(): bool
    {
        return (bool) ($this->settings['gamification_enabled'] ?? true);
    }

    /**
     * The IANA timezone defining this user's day boundary for streak math
     * (FR-GAM-01, FR-AUTH-04).
     */
    public function timezoneName(): string
    {
        return $this->timezone ?: 'UTC';
    }
}
