<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\Sprint;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * FR-ANL-03: the cross-goal personal dashboard — time distribution by
 * category and a weekly trend.
 *
 * Everything here is scoped to the acting member's own data. `/analytics/
 * overview` carries no Policy because it is self-scoped (02 §4), which makes
 * this class the only thing standing between a member and someone else's
 * numbers.
 */
class AnalyticsService
{
    public function __construct(private StreakService $streaks) {}

    /**
     * @return array<string, mixed>
     */
    public function overviewFor(User $user, int $trendDays = 28): array
    {
        $timezone = $user->timezoneName();
        $streak = $this->streaks->forUser($user);

        return [
            'totals' => $this->totals($user),
            'streak' => [
                'current' => $streak['current'],
                'longest' => $streak['longest'],
                'last_active_date' => $streak['last_active_date']?->toDateString(),
            ],
            'by_category' => $this->focusByCategory($user),
            'daily_trend' => $this->dailyTrend($user, $timezone, $trendDays),
            'gamification' => $user->hasGamificationEnabled()
                ? [
                    'enabled' => true,
                    'xp' => (int) $user->xp,
                    'level' => (int) $user->level,
                    'badges' => $user->badges()->get(['badges.key', 'badges.name'])
                        ->map(fn ($badge): array => ['key' => $badge->key, 'name' => $badge->name])
                        ->all(),
                ]
                : ['enabled' => false],
        ];
    }

    /**
     * @return array{total_focus_seconds: int, sessions_count: int, active_goals: int, completed_goals: int}
     */
    protected function totals(User $user): array
    {
        $sprints = Sprint::query()->completed()->where('user_id', $user->id);

        return [
            'total_focus_seconds' => (int) $sprints->clone()->sum('actual_duration_seconds'),
            'sessions_count' => (int) $sprints->clone()->count(),
            'active_goals' => Goal::query()->where('user_id', $user->id)->where('status', 'active')->count(),
            'completed_goals' => Goal::query()->where('user_id', $user->id)->where('status', 'completed')->count(),
        ];
    }

    /**
     * Focus seconds grouped by the goal's category. Sprints with no goal, or
     * on a goal with no category, are reported under a null key rather than
     * dropped — hiding them would make the pie chart quietly fail to add up
     * to the member's total.
     *
     * @return list<array{category: array{id: int, name: string}|null, focus_seconds: int}>
     */
    protected function focusByCategory(User $user): array
    {
        $rows = Sprint::query()
            ->completed()
            ->where('sprints.user_id', $user->id)
            ->leftJoin('goals', 'goals.id', '=', 'sprints.goal_id')
            ->leftJoin('categories', 'categories.id', '=', 'goals.category_id')
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.id as category_id, categories.name as category_name, SUM(sprints.actual_duration_seconds) as total_seconds')
            ->get();

        return $rows->map(fn ($row): array => [
            'category' => $row->category_id === null
                ? null
                : ['id' => (int) $row->category_id, 'name' => (string) $row->category_name],
            'focus_seconds' => (int) $row->total_seconds,
        ])->all();
    }

    /**
     * Focus minutes per local day, oldest first, with zero-activity days
     * present rather than missing.
     *
     * The gaps matter: a heatmap or trend line built from only the active days
     * would compress a fortnight of two sessions into a flattering
     * side-by-side pair (FR-ANL-01's heatmap, FR-ANL-03's weekly trend).
     *
     * @return list<array{date: string, focus_minutes: int}>
     */
    protected function dailyTrend(User $user, string $timezone, int $days): array
    {
        $days = max(1, min(365, $days));
        $start = CarbonImmutable::now($timezone)->startOfDay()->subDays($days - 1);

        $sprints = Sprint::query()
            ->completed()
            ->where('user_id', $user->id)
            ->whereNotNull('ended_at')
            ->where('ended_at', '>=', $start->utc())
            ->get(['ended_at', 'actual_duration_seconds']);

        $byDate = [];

        foreach ($sprints as $sprint) {
            $date = $sprint->ended_at->setTimezone($timezone)->toDateString();
            $byDate[$date] = ($byDate[$date] ?? 0) + (int) $sprint->actual_duration_seconds;
        }

        $trend = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->addDays($offset)->toDateString();

            $trend[] = [
                'date' => $date,
                'focus_minutes' => (int) floor(($byDate[$date] ?? 0) / 60),
            ];
        }

        return $trend;
    }
}
