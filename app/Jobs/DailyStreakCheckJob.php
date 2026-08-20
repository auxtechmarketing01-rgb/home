<?php

namespace App\Jobs;

use App\Models\Streak;
use App\Models\User;
use App\Notifications\StreakAtRiskNotification;
use App\Services\GamificationService;
use App\Services\StreakService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * FR-GAM-01, FR-GAM-03 and FR-NOT-02.
 *
 * Scheduled **hourly, not daily**, which sounds wrong for a job with "Daily"
 * in its name until you remember members are in different timezones (02 §6
 * describes it as fanning out per user). There is no single UTC hour that is
 * "evening" for everybody, so the job wakes every hour and acts only on the
 * members for whom it is now their configured reminder hour. `streaks
 * .last_at_risk_notified_on` holds their *local* date, which is what keeps an
 * hourly job from nagging twelve times.
 */
class DailyStreakCheckJob implements ShouldQueue
{
    use Queueable;

    public function handle(StreakService $streaks, GamificationService $gamification): void
    {
        $reminderHour = (int) config('pathforge.streaks.reminder_hour');

        User::query()
            ->whereNull('disabled_at')
            ->with('streak')
            ->chunkById(200, function ($users) use ($streaks, $gamification, $reminderHour): void {
                foreach ($users as $user) {
                    $this->refresh($user, $streaks, $gamification, $reminderHour);
                }
            });
    }

    protected function refresh(
        User $user,
        StreakService $streaks,
        GamificationService $gamification,
        int $reminderHour,
    ): void {
        $result = $streaks->forUser($user);

        $streak = $user->streak ?? new Streak;

        $streak->forceFill([
            'user_id' => $user->id,
            'current_streak' => $result['current'],
            /**
             * max() against the stored value, not a plain overwrite: the
             * longest streak is a historical high-water mark, and recomputing
             * it from a window of activity that has since been archived would
             * quietly take it away (FR-GOAL-03 drops archived goals from
             * streak logic).
             */
            'longest_streak' => max($result['longest'], (int) $streak->longest_streak),
            'last_active_date' => $result['last_active_date']?->toDateString(),
        ])->save();

        $gamification->recalculateFor($user);
        $gamification->awardBadges($user, (int) $streak->longest_streak);

        $this->remindIfAtRisk($user, $streak, $result, $reminderHour);
    }

    /**
     * FR-NOT-02: a nudge only once the member's own evening has arrived and
     * they still have nothing logged today.
     *
     * @param  array{current: int, longest: int, last_active_date: ?CarbonImmutable}  $result
     */
    protected function remindIfAtRisk(User $user, Streak $streak, array $result, int $reminderHour): void
    {
        $localNow = CarbonImmutable::now($user->timezoneName());
        $today = $localNow->toDateString();

        if ($localNow->hour < $reminderHour) {
            return;
        }

        /** Already active today — there is nothing at risk. */
        if ($result['last_active_date']?->toDateString() === $today) {
            return;
        }

        /** Already nagged today, in their local reckoning of "today". */
        if ($streak->last_at_risk_notified_on?->toDateString() === $today) {
            return;
        }

        $streak->forceFill(['last_at_risk_notified_on' => $today])->save();

        $user->notify(new StreakAtRiskNotification($result['current']));
    }
}
