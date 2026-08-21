<?php

use App\Jobs\CleanupStaleSprintsJob;
use App\Jobs\DailyStreakCheckJob;
use App\Jobs\NotifyExpiredSprintsJob;
use App\Jobs\RecalculateActiveGoalStatsJob;
use App\Jobs\SendRewardClaimReminderJob;
use App\Jobs\SendSprintReminderJob;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work (02 §6)
|--------------------------------------------------------------------------
|
| Two of these run hourly rather than daily even though they are conceptually
| daily jobs. That is because members are in different timezones: there is no
| single UTC hour that is "evening" for everybody, so the job wakes every hour
| and acts only on the members whose own local reminder hour it now is.
|
*/

/**
 * FR-SPR-10: one notification per sprint that has passed its planned
 * duration, then records that it sent it. It never touches `status`.
 */
Schedule::job(new NotifyExpiredSprintsJob)->everyMinute()->withoutOverlapping();

/**
 * Crash recovery only, with a full-day grace period. Read the class docblock
 * before shortening the interval or the grace: this is the single easiest
 * place to reintroduce "the pomodoro stops when you close the tab", which is
 * the bug FR-SPR-09 exists to prevent.
 */
Schedule::job(new CleanupStaleSprintsJob)->hourly()->withoutOverlapping();

/**
 * FR-GAM-01/03 and FR-NOT-02: refreshes each member's streak, awards badges,
 * and nudges anyone whose local evening has arrived with nothing logged.
 */
Schedule::job(new DailyStreakCheckJob)->hourly()->withoutOverlapping();

/**
 * Opt-in only — a member must have set `settings.sprint_reminder_hour`.
 */
Schedule::job(new SendSprintReminderJob)->hourly()->withoutOverlapping();

/**
 * Targets the most common real-world failure in every chore/reward app in the
 * research (01 §2): the mentor simply forgetting to deliver.
 */
Schedule::job(new SendRewardClaimReminderJob)->dailyAt('09:00')->withoutOverlapping();

/**
 * FR-ANL-02's "recompute nightly". Every other trigger for the stats rollup is
 * an event; the projected completion date and the streak also decay with time
 * alone, and nothing else would ever notice.
 */
Schedule::job(new RecalculateActiveGoalStatsJob)->dailyAt('02:00')->withoutOverlapping();
