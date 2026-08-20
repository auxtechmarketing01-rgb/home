<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\Sprint;
use Carbon\CarbonImmutable;

/**
 * Projected completion date (FR-ANL-02).
 *
 * The rule that matters more than the arithmetic: **return null rather than a
 * guess.** A date derived from one good evening is worse than no date at all,
 * because the member will plan around it. Every branch below that cannot
 * honestly answer returns null, and the UI renders that as "not enough data
 * yet" (03 §2 `ProjectionBanner`).
 */
class ProjectionService
{
    public function __construct(private StreakService $streaks) {}

    /**
     * Remaining estimated minutes divided by recent average daily focus
     * minutes, projected forward from today.
     */
    public function projectCompletionDate(Goal $goal): ?CarbonImmutable
    {
        $timezone = $goal->user?->timezoneName() ?? 'UTC';

        $remainingMinutes = $this->remainingEstimatedMinutes($goal);

        /**
         * Nothing left to estimate: either the plan is finished or it carries
         * no estimates at all. Neither supports a projection — the first
         * needs none, the second has nothing to divide.
         */
        if ($remainingMinutes <= 0) {
            return null;
        }

        $averageMinutesPerDay = $this->averageDailyFocusMinutes($goal, $timezone);

        /**
         * Guards the divide-by-zero case explicitly: a member with activity
         * in the window but zero recorded focus minutes gets null, not
         * INF converted into a date somewhere in the year 40000.
         */
        if ($averageMinutesPerDay === null || $averageMinutesPerDay <= 0.0) {
            return null;
        }

        $daysNeeded = (int) ceil($remainingMinutes / $averageMinutesPerDay);

        return CarbonImmutable::now($timezone)->startOfDay()->addDays($daysNeeded);
    }

    /**
     * Estimated minutes still outstanding. `skipped` items are excluded
     * along with `done` ones — a skipped item is work the member decided not
     * to do, so counting it would push the date out forever.
     */
    public function remainingEstimatedMinutes(Goal $goal): int
    {
        return (int) $goal->roadmapItems()
            ->whereNotIn('roadmap_items.status', ['done', 'skipped'])
            ->sum('roadmap_items.estimated_minutes');
    }

    /**
     * Average focus minutes per *calendar* day over the trailing window,
     * or null when the window holds fewer than the configured minimum of
     * distinct active days.
     *
     * Dividing by calendar days rather than by active days is deliberate: a
     * member who studies hard twice a week will finish at their two-days-a-
     * week pace, not at the pace of one of those evenings. Dividing by
     * active days would produce a confidently optimistic date, which is the
     * exact failure this method exists to avoid.
     */
    public function averageDailyFocusMinutes(Goal $goal, ?string $timezone = null): ?float
    {
        $timezone ??= $goal->user?->timezoneName() ?? 'UTC';

        $windowDays = max(1, (int) config('pathforge.projection.trailing_days'));
        $minimumDataPoints = max(1, (int) config('pathforge.projection.minimum_data_points'));

        $since = CarbonImmutable::now($timezone)->startOfDay()->subDays($windowDays - 1);

        $itemIds = $goal->roadmapItems()->pluck('roadmap_items.id');

        $sprints = Sprint::query()
            ->completed()
            ->whereNotNull('ended_at')
            ->where('ended_at', '>=', $since->utc())
            ->where(function ($query) use ($goal, $itemIds): void {
                $query->where('goal_id', $goal->id);

                if ($itemIds->isNotEmpty()) {
                    $query->orWhereIn('roadmap_item_id', $itemIds);
                }
            })
            ->get(['ended_at', 'actual_duration_seconds']);

        if ($sprints->isEmpty()) {
            return null;
        }

        $activeDays = $sprints
            ->map(fn (Sprint $sprint): string => $sprint->ended_at->setTimezone($timezone)->toDateString())
            ->unique()
            ->count();

        if ($activeDays < $minimumDataPoints) {
            return null;
        }

        $totalMinutes = $sprints->sum(
            fn (Sprint $sprint): float => ((int) $sprint->actual_duration_seconds) / 60
        );

        return $totalMinutes / $windowDays;
    }
}
