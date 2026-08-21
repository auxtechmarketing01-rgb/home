<?php

namespace App\Jobs;

use App\Models\Sprint;
use App\Models\User;
use App\Notifications\SprintReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 02 §6: "notify user if no sprint started by a configured time" — opt-in.
 *
 * Opt-in means exactly that: a member gets this only with
 * `settings.sprint_reminder_hour` set. Nobody is enrolled by default, because
 * an unrequested daily nudge is the shape of notification people mute the app
 * over.
 *
 * Runs hourly and fires at the member's own local hour, for the same reason
 * DailyStreakCheckJob does — there is no single UTC hour that is morning
 * everywhere.
 */
class SendSprintReminderJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        User::query()
            ->whereNull('disabled_at')
            ->whereNotNull('settings')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $this->remind($user);
                }
            });
    }

    protected function remind(User $user): void
    {
        $hour = $user->settings['sprint_reminder_hour'] ?? null;

        if (! is_int($hour)) {
            return;
        }

        $localNow = CarbonImmutable::now($user->timezoneName());

        if ($localNow->hour !== $hour) {
            return;
        }

        /** Any sprint started today, in their timezone, means no reminder. */
        $startedToday = Sprint::query()
            ->where('user_id', $user->id)
            ->where('started_at', '>=', $localNow->startOfDay()->utc())
            ->exists();

        if ($startedToday) {
            return;
        }

        $user->notify(new SprintReminderNotification($hour));
    }
}
