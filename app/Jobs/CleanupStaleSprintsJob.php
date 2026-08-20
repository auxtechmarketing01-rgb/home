<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\Sprint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Crash recovery, and nothing more (02 §6, FR-SPR-09).
 *
 * Read this before changing the condition below: **the planned duration is
 * not part of it, and must never become part of it.** A sprint three hours
 * past its 25-minute plan is a member who is still working; cancelling it is
 * the exact bug the "keeps running even when the site is closed" requirement
 * exists to prevent. The only thing this job cleans up is a session whose
 * browser or device is genuinely never coming back, which is why the grace
 * period is measured in a full day rather than minutes.
 *
 * Erring long costs a stale row somebody can cancel by hand. Erring short
 * silently destroys real work.
 */
class CleanupStaleSprintsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $graceHours = max(1, (int) config('pathforge.sprints.stale_grace_hours'));
        $cutoff = now()->subHours($graceHours);

        Sprint::query()
            ->active()
            ->where('started_at', '<=', $cutoff)
            ->chunkById(200, function ($sprints) use ($graceHours): void {
                foreach ($sprints as $sprint) {
                    $this->abandon($sprint, $graceHours);
                }
            });
    }

    protected function abandon(Sprint $sprint, int $graceHours): void
    {
        $sprint->forceFill([
            'status' => 'cancelled',
            'ended_at' => now(),
            'paused_at' => null,
            /** Cancelled sessions contribute no time, same as a manual cancel. */
            'actual_duration_seconds' => null,
        ])->save();

        ActivityLog::create([
            'user_id' => $sprint->user_id,
            'subject_type' => Sprint::class,
            'subject_id' => $sprint->id,
            'action' => 'sprint.abandoned',
            'meta' => ['grace_hours' => $graceHours],
        ]);

        Log::info('sprint.auto_cancelled_after_grace_period', [
            'sprint_id' => $sprint->id,
            'user_id' => $sprint->user_id,
            'started_at' => $sprint->started_at?->toIso8601String(),
            'grace_hours' => $graceHours,
        ]);
    }
}
