<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Badge;
use App\Models\Goal;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserBadge;

/**
 * FR-GAM-02 (XP and levels) and FR-GAM-03 (badges).
 *
 * Both are opt-in per member. The research behind this product specifically
 * flagged "gamification fatigue", so a member who has switched it off gets
 * nothing computed and nothing awarded — not a hidden tally waiting to
 * reappear.
 *
 * XP is **rebuilt from source**, never incremented, for the same reason as
 * the goal stats rollup: a missed or double-run pass would otherwise leave a
 * permanently wrong number with nothing to notice it.
 */
class GamificationService
{
    /**
     * @return array{xp: int, level: int}|null null when the member has opted out
     */
    public function recalculateFor(User $user): ?array
    {
        if (! $user->hasGamificationEnabled()) {
            return null;
        }

        $focusMinutes = (int) floor($this->totalFocusSeconds($user) / 60);
        $itemsCompleted = $this->completedItemCount($user);

        $xp = ($focusMinutes * (int) config('pathforge.gamification.xp_per_focus_minute'))
            + ($itemsCompleted * (int) config('pathforge.gamification.xp_per_roadmap_item'));

        $perLevel = max(1, (int) config('pathforge.gamification.xp_per_level'));
        $level = (int) floor($xp / $perLevel) + 1;

        /**
         * forceFill because `xp` and `level` are outside $fillable — they are
         * owned by this service and by nothing that handles a request body
         * (02 §5).
         */
        $user->forceFill(['xp' => $xp, 'level' => $level])->save();

        return ['xp' => $xp, 'level' => $level];
    }

    /**
     * FR-GAM-03. Idempotent: the composite unique on `user_badges` plus
     * firstOrCreate means running this every hour awards each badge once.
     *
     * @return list<string> keys of badges newly awarded
     */
    public function awardBadges(User $user, int $longestStreak): array
    {
        if (! $user->hasGamificationEnabled()) {
            return [];
        }

        $earnedKeys = [];

        foreach ([7 => 'streak_7', 30 => 'streak_30', 100 => 'streak_100'] as $days => $key) {
            if ($longestStreak >= $days) {
                $earnedKeys[] = $key;
            }
        }

        if (Goal::query()->where('user_id', $user->id)->where('status', 'completed')->exists()) {
            $earnedKeys[] = 'first_goal_completed';
        }

        if ($earnedKeys === []) {
            return [];
        }

        $badges = Badge::query()->whereIn('key', $earnedKeys)->get();
        $newlyAwarded = [];

        foreach ($badges as $badge) {
            $existed = UserBadge::query()
                ->where('user_id', $user->id)
                ->where('badge_id', $badge->id)
                ->exists();

            if ($existed) {
                continue;
            }

            $userBadge = new UserBadge;
            $userBadge->forceFill([
                'user_id' => $user->id,
                'badge_id' => $badge->id,
                'awarded_at' => now(),
            ])->save();

            $newlyAwarded[] = $badge->key;
        }

        return $newlyAwarded;
    }

    protected function totalFocusSeconds(User $user): int
    {
        $liveGoalIds = Goal::query()->where('user_id', $user->id)->select('id');

        return (int) Sprint::query()
            ->completed()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($liveGoalIds): void {
                $query->whereNull('goal_id')->orWhereIn('goal_id', $liveGoalIds);
            })
            ->sum('actual_duration_seconds');
    }

    /**
     * Counted from the activity feed rather than from current item statuses,
     * so an item completed on an archived goal still counts as work the
     * member did — XP earned is not taken back.
     */
    protected function completedItemCount(User $user): int
    {
        return (int) ActivityLog::query()
            ->where('user_id', $user->id)
            ->where('subject_type', RoadmapItem::class)
            ->where('action', 'roadmap_item.completed')
            ->distinct()
            ->count('subject_id');
    }
}
