<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Sprint;
use App\Models\Streak;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * FR-GRP-03. Cached under `leaderboard:{group_id}:{period}` with a short TTL
 * and invalidated explicitly by RecalculateGoalStatsJob, so it feels live
 * without being recomputed on every page view (02 §7).
 *
 * ## The privacy rule this class exists to enforce
 *
 * Focus minutes and goals-completed are aggregated **only over goals shared
 * with this group** — `visibility = 'group'` and `group_id` matching. A
 * private goal's time must never reach another member's leaderboard, not even
 * as an anonymous number, because a total that moves without a visible cause
 * still discloses hidden activity (01 §5 Privacy, FR-GRP-02).
 *
 * `current_streak` is the deliberate exception: it is the member's own
 * overall streak, the same number they see on their dashboard. A streak
 * computed from group-visible activity alone would read 0 for someone who
 * studies daily on private goals, which makes the metric actively
 * misleading — and FR-GRP-03 names streaks as a leaderboard metric. It
 * discloses only "this person showed up", which is the entire purpose of
 * putting it on a leaderboard.
 */
class LeaderboardService
{
    /**
     * @var list<string>
     */
    public const PERIODS = ['week', 'month', 'all'];

    /**
     * @return list<array{user: array{id: int, name: string}, focus_minutes: int, current_streak: int, goals_completed: int}>
     */
    public function forGroup(Group $group, string $period = 'week'): array
    {
        $period = in_array($period, self::PERIODS, true) ? $period : 'week';

        return Cache::store(config('pathforge.leaderboard.store'))->remember(
            $this->cacheKey($group->id, $period),
            (int) config('pathforge.leaderboard.ttl_seconds'),
            fn (): array => $this->compute($group, $period),
        );
    }

    /**
     * Called from RecalculateGoalStatsJob for every group the affected
     * member belongs to, rather than waiting for the TTL (02 §7).
     */
    public function invalidateForUser(User $user): void
    {
        $groupIds = GroupMember::query()->where('user_id', $user->id)->pluck('group_id');

        foreach ($groupIds as $groupId) {
            $this->forgetGroup((int) $groupId);
        }
    }

    public function forgetGroup(int $groupId): void
    {
        $store = Cache::store(config('pathforge.leaderboard.store'));

        foreach (self::PERIODS as $period) {
            $store->forget($this->cacheKey($groupId, $period));
        }
    }

    public function cacheKey(int $groupId, string $period): string
    {
        return "leaderboard:{$groupId}:{$period}";
    }

    /**
     * @return list<array{user: array{id: int, name: string}, focus_minutes: int, current_streak: int, goals_completed: int}>
     */
    protected function compute(Group $group, string $period): array
    {
        $members = $group->members()->orderBy('name')->get(['users.id', 'users.name']);
        $memberIds = $members->pluck('id')->all();

        if ($memberIds === []) {
            return [];
        }

        $since = $this->since($period);

        /**
         * The subquery that does the privacy work: only goals this group can
         * see. Every aggregate below is bounded by it.
         */
        $sharedGoalIds = Goal::query()->sharedWithGroup($group->id)->select('goals.id');

        $focusSeconds = Sprint::query()
            ->completed()
            ->whereIn('user_id', $memberIds)
            ->whereIn('goal_id', $sharedGoalIds)
            ->when($since !== null, fn ($query) => $query->where('ended_at', '>=', $since))
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(actual_duration_seconds) as total_seconds')
            ->pluck('total_seconds', 'user_id');

        $goalsCompleted = Goal::query()
            ->sharedWithGroup($group->id)
            ->whereIn('user_id', $memberIds)
            ->where('status', 'completed')
            ->when($since !== null, fn ($query) => $query->where('completed_at', '>=', $since))
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as goal_count')
            ->pluck('goal_count', 'user_id');

        $streaks = Streak::query()
            ->whereIn('user_id', $memberIds)
            ->pluck('current_streak', 'user_id');

        $entries = $members->map(fn (User $member): array => [
            'user' => ['id' => $member->id, 'name' => $member->name],
            'focus_minutes' => (int) floor(((int) ($focusSeconds[$member->id] ?? 0)) / 60),
            'current_streak' => (int) ($streaks[$member->id] ?? 0),
            'goals_completed' => (int) ($goalsCompleted[$member->id] ?? 0),
        ])->all();

        usort($entries, function (array $a, array $b): int {
            return [$b['focus_minutes'], $b['current_streak'], $a['user']['name']]
                <=> [$a['focus_minutes'], $a['current_streak'], $b['user']['name']];
        });

        return $entries;
    }

    /**
     * Rolling windows rather than calendar weeks/months, and in UTC.
     *
     * A group can span timezones, so "this week" has no single answer — one
     * member's Monday is another's Sunday, and whichever boundary is chosen
     * makes the table briefly disagree with itself. A rolling window is the
     * same length for everyone and never has that problem.
     */
    protected function since(string $period): ?CarbonImmutable
    {
        return match ($period) {
            'week' => CarbonImmutable::now()->subDays(7),
            'month' => CarbonImmutable::now()->subDays(30),
            default => null,
        };
    }
}
