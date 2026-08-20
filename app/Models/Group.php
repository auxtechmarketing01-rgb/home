<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /**
     * `owner_id` and `invite_code` are set by the Action that creates the
     * group — a client must never be able to nominate an owner or choose a
     * code.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<GroupMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Goal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * @return HasMany<Challenge, $this>
     */
    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }

    /**
     * Groups the given member belongs to — the query-side mirror of
     * GroupPolicy::view.
     *
     * @param  Builder<Group>  $query
     * @return Builder<Group>
     */
    public function scopeForMember(Builder $query, User $user): Builder
    {
        return $query->whereIn('groups.id', GroupMember::query()
            ->where('user_id', $user->id)
            ->select('group_id'));
    }

    public function hasMember(User $user): bool
    {
        return $this->memberships()->where('user_id', $user->id)->exists();
    }

    /**
     * FR-GRP-01: codes are regenerable, so an owner can invalidate one that
     * has been shared too widely.
     */
    public static function generateInviteCode(): string
    {
        $length = max(6, (int) config('pathforge.groups.invite_code_length'));

        do {
            $code = strtoupper(Str::random($length));
        } while (static::query()->where('invite_code', $code)->exists());

        return $code;
    }
}
