<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SprintFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sprint extends Model
{
    /** @use HasFactory<SprintFactory> */
    use HasFactory;

    /**
     * Every lifecycle column is written by an Action, never mass-assigned:
     * `started_at`/`ended_at`/`status`/`paused_at`/`paused_seconds_total`/
     * `actual_duration_seconds` are the timer itself, and
     * `notified_expired_at` belongs to NotifyExpiredSprintsJob (02 §3).
     *
     * @var list<string>
     */
    protected $fillable = [
        'goal_id',
        'roadmap_item_id',
        'mode',
        'planned_duration_seconds',
        'break_seconds',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'break_seconds' => 0,
        'paused_seconds_total' => 0,
        'status' => 'running',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'paused_at' => 'immutable_datetime',
            'notified_expired_at' => 'immutable_datetime',
            /**
             * Carbon 3 returns floats from its diff methods, so every second
             * count is cast here as well as being floored where it is
             * computed. These columns are unsigned integers in the database;
             * a model that hands back 300.0738 before the row is reloaded
             * would make callers and tests disagree with storage.
             */
            'planned_duration_seconds' => 'integer',
            'break_seconds' => 'integer',
            'paused_seconds_total' => 'integer',
            'actual_duration_seconds' => 'integer',
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
     * The only definition of when a sprint reaches its plan. Both
     * NotifyExpiredSprintsJob and the SPA's `useFocusTimer` (03 §4) derive
     * the deadline this way.
     *
     * Paused time is deliberately *not* added back in. It keeps the server
     * and the client agreeing on one number, and it is what FR-SPR-03 means
     * by "the stored `started_at` + `planned_duration_seconds` is the source
     * of truth". Passing the deadline is a notification, never a state
     * change, so an early notification costs a member nothing.
     */
    public function deadlineAt(): ?CarbonImmutable
    {
        if ($this->planned_duration_seconds === null || $this->started_at === null) {
            return null;
        }

        return $this->started_at->addSeconds($this->planned_duration_seconds);
    }

    /**
     * Overtime is derived, never stored (02 §3, FR-SPR-09).
     */
    public function isOvertime(): bool
    {
        $deadline = $this->deadlineAt();

        return $this->isActive()
            && $deadline !== null
            && $deadline->isPast();
    }

    public function overtimeSeconds(): int
    {
        $deadline = $this->deadlineAt();

        if ($deadline === null || ! $deadline->isPast()) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($deadline, absolute: true));
    }

    /**
     * Running or paused — the two states that occupy the member's single
     * active-sprint slot (FR-SPR-08).
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['running', 'paused'], true);
    }

    /**
     * Focus seconds accrued so far, excluding paused time and the pause
     * currently in progress (FR-SPR-04). Used both to stamp
     * `actual_duration_seconds` on completion and to report progress on a
     * still-running sprint.
     */
    public function focusSecondsAt(CarbonImmutable $moment): int
    {
        if ($this->started_at === null) {
            return 0;
        }

        $elapsed = max(0, (int) $moment->diffInSeconds($this->started_at, absolute: true));

        $paused = (int) $this->paused_seconds_total;

        if ($this->status === 'paused' && $this->paused_at !== null) {
            $paused += max(0, (int) $moment->diffInSeconds($this->paused_at, absolute: true));
        }

        return max(0, $elapsed - $paused);
    }

    /**
     * @param  Builder<Sprint>  $query
     * @return Builder<Sprint>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('sprints.status', ['running', 'paused']);
    }

    /**
     * @param  Builder<Sprint>  $query
     * @return Builder<Sprint>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('sprints.status', 'completed');
    }
}
