<?php

namespace App\Models;

use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory, SoftDeletes;

    /**
     * `user_id` is set from the authenticated user by CreateGoalAction and
     * `completed_at` by CompleteGoalAction — neither is mass-assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        /**
         * Which group a `group`-visible goal is shared with. Fillable, but
         * StoreGoalRequest/UpdateGoalRequest only accept a group the acting
         * member actually belongs to — otherwise a member could publish a
         * goal into a circle they are not part of.
         */
        'group_id',
        'title',
        'description',
        'status',
        'visibility',
        'target_start_date',
        'target_end_date',
    ];

    /**
     * Mirrors the column defaults from the migration so a freshly created
     * Goal reports the same status and visibility the database would give
     * it — without this the create response returns nulls for fields the row
     * actually has (02 §3).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'visibility' => 'private',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_start_date' => 'date',
            'target_end_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return HasMany<Reward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }

    /**
     * @return HasOne<Roadmap, $this>
     */
    public function roadmap(): HasOne
    {
        return $this->hasOne(Roadmap::class);
    }

    /**
     * @return HasManyThrough<RoadmapItem, Roadmap, $this>
     */
    public function roadmapItems(): HasManyThrough
    {
        return $this->hasManyThrough(RoadmapItem::class, Roadmap::class);
    }

    /**
     * The analytics cache. Read it; never recompute its columns live
     * (02 §7).
     *
     * @return HasOne<GoalStats, $this>
     */
    public function stats(): HasOne
    {
        return $this->hasOne(GoalStats::class);
    }

    /**
     * @return HasMany<Sprint, $this>
     */
    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    /**
     * @return MorphMany<ResourceFile, $this>
     */
    public function resourceFiles(): MorphMany
    {
        return $this->morphMany(ResourceFile::class, 'resourceable');
    }

    /**
     * Goals a given member may see — the query-side mirror of
     * GoalPolicy::view (01 §5 Privacy).
     *
     * The three grants are independent and must stay that way, matching the
     * policy branch for branch:
     *
     * 1. The owner.
     * 2. A member of the group a `group`-visible goal was shared with
     *    (FR-GRP-02). Only that goal's own group counts, not every group the
     *    viewer happens to be in.
     * 3. A mentor with an `accepted` mentorship over the owner (FR-MENT-04).
     *    This one deliberately ignores `visibility`: mentorship is an
     *    explicit grant of read access, so it reaches a `private` goal too.
     *    That is the point of it, not an oversight.
     *
     * Policies guard single-record routes; this scope guards every list,
     * comparison view and leaderboard. A branch added to one without the
     * other is how a private goal ends up in someone else's response.
     *
     * @param  Builder<Goal>  $query
     * @return Builder<Goal>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where('goals.user_id', $user->id)
                ->orWhere(function (Builder $query) use ($user): void {
                    $query->where('goals.visibility', 'group')
                        ->whereNotNull('goals.group_id')
                        ->whereIn('goals.group_id', $user->groupIdsQuery());
                })
                ->orWhereIn('goals.user_id', Mentorship::query()
                    ->accepted()
                    ->where('mentor_id', $user->id)
                    ->select('mentee_id'));
        });
    }

    /**
     * Goals shared with a specific group. Used by the comparison views and
     * the leaderboard, which must never widen to a member's private goals
     * even when the viewer can see other goals of theirs.
     *
     * @param  Builder<Goal>  $query
     * @return Builder<Goal>
     */
    public function scopeSharedWithGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('goals.visibility', 'group')
            ->where('goals.group_id', $groupId);
    }

    /**
     * @param  Builder<Goal>  $query
     * @return Builder<Goal>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('goals.user_id', $user->id);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
