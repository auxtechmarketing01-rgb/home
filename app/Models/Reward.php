<?php

namespace App\Models;

use Database\Factories\RewardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FR-RWD-01..07.
 *
 * A bookkeeping record, never a wallet: `monetary_amount` says what was
 * promised so a parent does not have to remember it, and marking a reward
 * `fulfilled` records that something happened outside the app. No balance
 * exists and nothing is ever spent here (01 NFR Financial integrity).
 */
class Reward extends Model
{
    /** @use HasFactory<RewardFactory> */
    use HasFactory;

    /**
     * `status`, `requested_by`, `claimed_at`, `fulfilled_at`,
     * `fulfilled_note` and `mentorship_id` are all owned by the Rewards
     * Actions — one Action per transition. Allowing any of them to be
     * mass-assigned would let a client jump the state machine, which is the
     * one thing the state machine exists to prevent.
     *
     * @var list<string>
     */
    protected $fillable = [
        'goal_id',
        'roadmap_item_id',
        'title',
        'description',
        'type',
        'monetary_amount',
        'currency_label',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monetary_amount' => 'decimal:2',
            'claimed_at' => 'immutable_datetime',
            'fulfilled_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Mentorship, $this>
     */
    public function mentorship(): BelongsTo
    {
        return $this->belongsTo(Mentorship::class);
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * @return BelongsTo<RoadmapItem, $this>
     */
    public function roadmapItem(): BelongsTo
    {
        return $this->belongsTo(RoadmapItem::class);
    }

    /**
     * Rewards where the member is on either side of the mentorship.
     *
     * @param  Builder<Reward>  $query
     * @return Builder<Reward>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereIn('rewards.mentorship_id', Mentorship::query()
            ->involving($user)
            ->select('id'));
    }

    /**
     * @param  Builder<Reward>  $query
     * @return Builder<Reward>
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('rewards.status', $status);
    }

    /**
     * FR-RWD-06: the ledger counts only rewards actually delivered, so an
     * `earned` or `claimed` monetary reward is a promise, not a debt settled.
     *
     * @param  Builder<Reward>  $query
     * @return Builder<Reward>
     */
    public function scopeFulfilledMonetary(Builder $query): Builder
    {
        return $query->where('rewards.status', 'fulfilled')
            ->where('rewards.type', 'monetary')
            ->whereNotNull('rewards.monetary_amount');
    }
}
