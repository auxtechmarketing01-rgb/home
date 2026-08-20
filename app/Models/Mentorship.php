<?php

namespace App\Models;

use Database\Factories\MentorshipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A relationship between two specific people, not a role (01 §4.7).
 *
 * Only `accepted` grants anything. `pending`, `declined` and `ended` rows
 * grant no read access, no assignment rights and no reward abilities — which
 * is why every query here goes through `accepted()` rather than merely
 * checking that a row exists.
 */
class Mentorship extends Model
{
    /** @use HasFactory<MentorshipFactory> */
    use HasFactory;

    /**
     * Nothing here is user-supplied content: both parties, the initiator and
     * the status are all resolved by the Mentorships Actions after
     * authorization.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'responded_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return HasMany<Reward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function involves(User $user): bool
    {
        return $this->mentor_id === $user->id || $this->mentee_id === $user->id;
    }

    public function isMentor(User $user): bool
    {
        return $this->mentor_id === $user->id;
    }

    public function isMentee(User $user): bool
    {
        return $this->mentee_id === $user->id;
    }

    /**
     * FR-MENT-02: only the party who did *not* initiate may accept or
     * decline. Without this an eager requester could simply approve
     * themselves into someone else's goals.
     */
    public function isInitiator(User $user): bool
    {
        return $this->requested_by_user_id === $user->id;
    }

    /**
     * @param  Builder<Mentorship>  $query
     * @return Builder<Mentorship>
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('mentorships.status', 'accepted');
    }

    /**
     * Rows where the given member is on either side.
     *
     * @param  Builder<Mentorship>  $query
     * @return Builder<Mentorship>
     */
    public function scopeInvolving(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where('mentorships.mentor_id', $user->id)
                ->orWhere('mentorships.mentee_id', $user->id);
        });
    }

    /**
     * Does an accepted mentorship exist where `$mentor` mentors `$mentee`?
     * The single question GoalPolicy's mentorship branch and
     * RoadmapItemPolicy::assign both ask.
     */
    public static function acceptedBetween(User $mentor, int $menteeId): bool
    {
        return static::query()
            ->accepted()
            ->where('mentor_id', $mentor->id)
            ->where('mentee_id', $menteeId)
            ->exists();
    }
}
