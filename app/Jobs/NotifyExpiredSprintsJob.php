<?php

namespace App\Jobs;

use App\Models\Sprint;
use App\Notifications\SprintCompleteNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * FR-SPR-10. Scheduled every minute.
 *
 * This job does exactly two things: send one notification, and record that it
 * sent it. It must never touch `status` — reaching the planned duration is a
 * notification event, not the end of the session (FR-SPR-09, 02 §6). This job
 * is what makes "still running until the member stops it" true *and* useful:
 * they find out the plan was reached without the session being taken away
 * from them.
 */
class NotifyExpiredSprintsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        /**
         * The candidate set is filtered in PHP rather than with SQL date
         * arithmetic on purpose. `started_at + planned_duration_seconds`
         * needs DATE_ADD on MySQL/MariaDB and datetime() on SQLite, and 06
         * §1.1 flags dialect drift as something to avoid rather than
         * discover in production. It costs nothing here: FR-SPR-08 caps this
         * set at one row per member, and only members with a sprint actually
         * in flight appear in it.
         */
        Sprint::query()
            ->where('status', 'running')
            ->whereNull('notified_expired_at')
            ->whereNotNull('planned_duration_seconds')
            ->where('started_at', '<=', now())
            ->with(['user', 'goal', 'roadmapItem'])
            ->chunkById(200, function ($sprints): void {
                foreach ($sprints as $sprint) {
                    if (! $sprint->deadlineAt()?->isPast()) {
                        continue;
                    }

                    $this->notifyOnce($sprint);
                }
            });
    }

    /**
     * Claim first, then notify.
     *
     * The conditional update is the dedup mechanism: it only succeeds for
     * whoever finds `notified_expired_at` still null, so two overlapping runs
     * of this job cannot both notify for the same sprint. Setting the
     * timestamp after sending would leave exactly that window open, and the
     * symptom would be a member getting the same alert every minute while
     * they work in overtime.
     */
    protected function notifyOnce(Sprint $sprint): void
    {
        $claimed = Sprint::query()
            ->whereKey($sprint->id)
            ->whereNull('notified_expired_at')
            ->update(['notified_expired_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $sprint->user?->notify(new SprintCompleteNotification($sprint));
    }
}
