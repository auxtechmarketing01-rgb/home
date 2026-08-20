<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Streak arithmetic (FR-GAM-01).
 *
 * The whole difficulty here is the day boundary. A streak is a count of
 * consecutive *local* days, and "local" means the member's own
 * `users.timezone` — two members can cross midnight at different UTC
 * instants, so grouping timestamps by their UTC date silently breaks the
 * streak for anyone not on UTC. Every timestamp is therefore converted to
 * the goal owner's timezone before its date is taken.
 */
class StreakService
{
    /**
     * Current and longest run of qualifying days for one goal.
     *
     * @return array{current: int, longest: int, last_active_date: ?CarbonImmutable}
     */
    public function forGoal(Goal $goal): array
    {
        $timezone = $goal->user?->timezoneName() ?? 'UTC';
        $dates = $this->qualifyingDatesForGoal($goal, $timezone);

        return [
            'current' => $this->currentRun($dates, $timezone),
            'longest' => $this->longestRun($dates),
            'last_active_date' => $dates->last(),
        ];
    }

    /**
     * The distinct local dates on which this goal saw qualifying activity,
     * ascending. Qualifying means what FR-GAM-01 says: at least one
     * completed Sprint, or at least one Roadmap Item marked done.
     *
     * Item completions are read from the activity feed rather than from
     * `roadmap_items.updated_at`, because any later edit to the row would
     * move that timestamp and fabricate activity on a day nothing happened.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function qualifyingDatesForGoal(Goal $goal, ?string $timezone = null): Collection
    {
        $timezone ??= $goal->user?->timezoneName() ?? 'UTC';

        $itemIds = $goal->roadmapItems()->pluck('roadmap_items.id');

        $sprintEndings = $goal->sprints()
            ->completed()
            ->whereNotNull('ended_at')
            ->pluck('ended_at');

        if ($itemIds->isNotEmpty()) {
            $sprintEndings = $sprintEndings->merge(
                Sprint::query()
                    ->completed()
                    ->whereNotNull('ended_at')
                    ->whereIn('roadmap_item_id', $itemIds)
                    ->pluck('ended_at')
            );
        }

        $itemCompletions = $itemIds->isEmpty()
            ? collect()
            : ActivityLog::query()
                ->where('subject_type', RoadmapItem::class)
                ->whereIn('subject_id', $itemIds)
                ->where('action', 'roadmap_item.completed')
                ->pluck('created_at');

        return $this->toLocalDates($sprintEndings->merge($itemCompletions), $timezone);
    }

    /**
     * Collapses a set of UTC instants into the distinct local dates they fall
     * on, ascending. This conversion is the whole reason streaks are correct
     * for two members in different countries.
     *
     * @param  Collection<int, mixed>  $timestamps
     * @return Collection<int, CarbonImmutable>
     */
    protected function toLocalDates(Collection $timestamps, string $timezone): Collection
    {
        return $timestamps
            ->filter()
            ->map(fn ($timestamp): string => CarbonImmutable::parse($timestamp)
                ->setTimezone($timezone)
                ->toDateString())
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $date): CarbonImmutable => CarbonImmutable::parse($date, $timezone)->startOfDay());
    }

    /**
     * Current and longest run of qualifying days for a *member*, across every
     * goal they own (FR-GAM-01). This is what the leaderboard and the
     * gamification pass read, as opposed to the per-goal streak on
     * `goal_stats`.
     *
     * @return array{current: int, longest: int, last_active_date: ?CarbonImmutable}
     */
    public function forUser(User $user): array
    {
        $timezone = $user->timezoneName();
        $dates = $this->qualifyingDatesForUser($user, $timezone);

        return [
            'current' => $this->currentRun($dates, $timezone),
            'longest' => $this->longestRun($dates),
            'last_active_date' => $dates->last(),
        ];
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    public function qualifyingDatesForUser(User $user, ?string $timezone = null): Collection
    {
        $timezone ??= $user->timezoneName();

        /**
         * FR-GOAL-03: archiving a goal stops it counting toward the streak.
         * `Goal::query()` already excludes soft-deleted rows, so restricting
         * to those ids drops archived work — while a sprint with no goal at
         * all (a general focus session, FR-SPR-01) still counts, because the
         * member genuinely showed up.
         */
        $liveGoalIds = Goal::query()->where('user_id', $user->id)->select('id');

        $sprintEndings = Sprint::query()
            ->completed()
            ->where('user_id', $user->id)
            ->whereNotNull('ended_at')
            ->where(function ($query) use ($liveGoalIds): void {
                $query->whereNull('goal_id')->orWhereIn('goal_id', $liveGoalIds);
            })
            ->pluck('ended_at');

        $liveItemIds = RoadmapItem::query()
            ->whereIn('roadmap_id', Roadmap::query()
                ->whereIn('goal_id', Goal::query()->where('user_id', $user->id)->select('id'))
                ->select('id'))
            ->select('id');

        $itemCompletions = ActivityLog::query()
            ->where('user_id', $user->id)
            ->where('subject_type', RoadmapItem::class)
            ->where('action', 'roadmap_item.completed')
            ->whereIn('subject_id', $liveItemIds)
            ->pluck('created_at');

        return $this->toLocalDates($sprintEndings->merge($itemCompletions), $timezone);
    }

    /**
     * The run ending today or yesterday, in the member's timezone.
     *
     * Yesterday counts: a member who has not studied yet *today* has not
     * broken anything — they simply have not acted yet. Ending the streak at
     * midnight would punish everyone who studies in the evening.
     *
     * @param  Collection<int, CarbonImmutable>  $dates
     */
    public function currentRun(Collection $dates, string $timezone): int
    {
        if ($dates->isEmpty()) {
            return 0;
        }

        $today = CarbonImmutable::now($timezone)->startOfDay();
        $last = $dates->last();

        $gapFromToday = (int) $last->diffInDays($today, absolute: false);

        if ($gapFromToday > 1) {
            return 0;
        }

        $run = 1;
        $reversed = $dates->reverse()->values();

        for ($index = 1; $index < $reversed->count(); $index++) {
            $expected = $reversed[$index - 1]->subDay();

            if (! $reversed[$index]->isSameDay($expected)) {
                break;
            }

            $run++;
        }

        return $run;
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $dates
     */
    public function longestRun(Collection $dates): int
    {
        if ($dates->isEmpty()) {
            return 0;
        }

        $longest = 1;
        $run = 1;

        for ($index = 1; $index < $dates->count(); $index++) {
            if ($dates[$index]->isSameDay($dates[$index - 1]->addDay())) {
                $run++;
            } else {
                $run = 1;
            }

            $longest = max($longest, $run);
        }

        return $longest;
    }
}
