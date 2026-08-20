<?php

namespace Database\Seeders;

use App\Actions\Goals\CreateGoalAction;
use App\Actions\Groups\CreateGroupAction;
use App\Actions\Groups\JoinGroupAction;
use App\Actions\Mentorships\RequestMentorshipAction;
use App\Actions\Mentorships\RespondToMentorshipAction;
use App\Actions\Rewards\CreateRewardAction;
use App\Actions\Roadmaps\AssignRoadmapItemAction;
use App\Actions\Roadmaps\CreateRoadmapItemAction;
use App\Actions\Sprints\CompleteSprintAction;
use App\Actions\Sprints\StartSprintAction;
use App\Jobs\DailyStreakCheckJob;
use App\Jobs\RecalculateGoalStatsJob;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Group;
use App\Models\RoadmapItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Local-development fixture covering all four phases: a group of three, goals
 * with roadmaps, completed focus sessions, a mentorship, a mentor assignment
 * and a reward part-way through its state machine.
 *
 * Everything is built through the same Actions the API uses, so the seeded
 * data can never drift from what the endpoints actually produce — if an Action
 * gains a rule, this seeder starts obeying it for free (or starts failing,
 * which is also useful).
 *
 * Notifications are faked for the duration: seeding should not queue a few
 * dozen pushes at a developer, and a fresh database has no push subscriptions
 * to send them to anyway.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Notification::fake();

        $parent = $this->member('parent@pathforge.test', 'Amina (parent)', 'Asia/Dhaka');
        $elder = $this->member('elder@pathforge.test', 'Bilal', 'Asia/Dhaka');
        $younger = $this->member('younger@pathforge.test', 'Chowdhury', 'Asia/Dhaka');

        if ($parent->goals()->exists()) {
            return;
        }

        /** FR-GRP-01: the family circle everything else is scoped through. */
        $group = app(CreateGroupAction::class)($parent, ['name' => 'The Rahmans']);
        app(JoinGroupAction::class)($elder, $group->invite_code);
        app(JoinGroupAction::class)($younger, $group->invite_code);

        $cGoal = $this->learnCGoal($elder, $group);
        $this->fitnessGoal($younger, $group);
        $this->readingGoal($parent);

        $this->mentorship($parent, $elder, $cGoal);

        $this->materialiseRollups();

        /** Fills in the streak rows and badges the dashboard reads. */
        app()->call([new DailyStreakCheckJob, 'handle']);
    }

    /**
     * Runs the recalculation inline for every seeded goal.
     *
     * The Actions above dispatch RecalculateGoalStatsJob to the queue, which is
     * correct for the app (02 §6 — the member must never wait on it) but wrong
     * for a seeder: on a machine with no worker running, seeding would leave
     * sprints with no `goal_stats` rows, so every analytics screen, the
     * leaderboard and the mentor dashboard would come up empty and look broken.
     *
     * Called through the container rather than dispatched so it bypasses both
     * the queue and the ShouldBeUnique lock.
     */
    protected function materialiseRollups(): void
    {
        Goal::query()->with('roadmap')->each(function (Goal $goal): void {
            app()->call([new RecalculateGoalStatsJob($goal), 'handle']);
        });
    }

    protected function member(string $email, string $name, string $timezone): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'timezone' => $timezone,
                'email_verified_at' => now(),
            ],
        );
    }

    /**
     * The journey from 01 §3.3, with real focus time so the analytics
     * endpoints have something to show.
     */
    protected function learnCGoal(User $user, Group $group): Goal
    {
        $goal = app(CreateGoalAction::class)($user, [
            'category_id' => $this->category('Programming')?->id,
            'group_id' => $group->id,
            'title' => 'Learn C Programming',
            'description' => 'Two months, day by day, from variables to pointers.',
            'visibility' => 'group',
            'target_start_date' => now()->subWeeks(2)->toDateString(),
            'target_end_date' => now()->addMonths(2)->toDateString(),
        ]);

        $days = [
            'Day 1 – Variables & types',
            'Day 2 – Control flow',
            'Day 3 – Functions',
            'Day 4 – Arrays & strings',
            'Day 5 – Pointers, part one',
            'Day 6 – Pointers, part two',
        ];

        foreach ($days as $index => $title) {
            app(CreateRoadmapItemAction::class)($user, $goal->roadmap, [
                'title' => $title,
                'day_number' => $index + 1,
                'estimated_minutes' => 90,
                'position' => $index + 1,
            ]);
        }

        $items = $goal->roadmap->items()->orderBy('position')->get();

        /**
         * Sessions spread over consecutive days so the streak, the heatmap and
         * the projection all have enough distinct data points to be non-null
         * (ProjectionService needs at least `minimum_data_points` active days).
         */
        foreach ([5, 4, 3, 2, 1] as $offset) {
            $item = $items[min(4 - ($offset - 1), $items->count() - 1)];

            $this->logSession($user, $item, $offset, 45 * 60);
        }

        /** First two days finished; the third is under way. */
        $items[0]->forceFill(['status' => 'done'])->save();
        $items[1]->forceFill(['status' => 'done'])->save();
        $items[2]->forceFill(['status' => 'in_progress'])->save();

        $goal->roadmap->items()->whereKey($items[3]->id)->update(['status' => 'skipped']);

        return $goal;
    }

    protected function fitnessGoal(User $user, Group $group): void
    {
        $goal = app(CreateGoalAction::class)($user, [
            'category_id' => $this->category('Fitness')?->id,
            'group_id' => $group->id,
            'title' => 'Run 5k without stopping',
            'visibility' => 'group',
        ]);

        foreach (['Week 1 – Walk/run intervals', 'Week 2 – 2k continuous', 'Week 3 – 3.5k'] as $index => $title) {
            app(CreateRoadmapItemAction::class)($user, $goal->roadmap, [
                'title' => $title,
                'estimated_minutes' => 40,
                'position' => $index + 1,
            ]);
        }

        $first = $goal->roadmap->items()->orderBy('position')->first();

        foreach ([3, 2] as $offset) {
            $this->logSession($user, $first, $offset, 30 * 60);
        }
    }

    /**
     * Deliberately private, so the leaderboard tests of privacy have a
     * real-world counterpart a developer can eyeball: this goal's time must
     * never show up in the group view.
     */
    protected function readingGoal(User $user): void
    {
        $goal = app(CreateGoalAction::class)($user, [
            'category_id' => $this->category('Reading')?->id,
            'title' => 'Finish the Sirat collection',
            'visibility' => 'private',
        ]);

        app(CreateRoadmapItemAction::class)($user, $goal->roadmap, [
            'title' => 'Volume one',
            'estimated_minutes' => 300,
            'position' => 1,
        ]);

        $this->logSession($user, $goal->roadmap->items()->first(), 1, 60 * 60);
    }

    /**
     * FR-MENT-01..06 and FR-RWD-01: the parent mentors the elder sibling, sets
     * an expectation on one item, and attaches a reward to another.
     *
     * The reward is left `offered` rather than walked all the way to
     * `fulfilled`: `offered` is the state where the app has something to do
     * next, which is what makes the seeded data useful to click through.
     */
    protected function mentorship(User $parent, User $mentee, Goal $goal): void
    {
        $mentorship = app(RequestMentorshipAction::class)($mentee, $parent, 'mentor');
        app(RespondToMentorshipAction::class)($parent, $mentorship, true);

        $items = $goal->roadmap->items()->orderBy('position')->get();

        app(AssignRoadmapItemAction::class)($parent, $items[2], [
            'assigned_minutes' => 120,
            'assigned_due_at' => now()->addWeek(),
        ]);

        app(CreateRewardAction::class)($parent, $mentorship, [
            'roadmap_item_id' => $items[4]->id,
            'title' => '500 taka for finishing pointers',
            'description' => 'Pointers are the hard part. Get through it.',
            'type' => 'monetary',
            'monetary_amount' => 500,
            'currency_label' => 'BDT',
        ]);

        app(CreateRewardAction::class)($parent, $mentorship, [
            'goal_id' => $goal->id,
            'title' => 'Cricket match tickets',
            'type' => 'privilege',
        ]);
    }

    /**
     * Starts and completes a real sprint at a point in the past, so the rollup
     * job has genuine rows to aggregate rather than hand-written totals.
     */
    protected function logSession(User $user, RoadmapItem $item, int $daysAgo, int $seconds): void
    {
        $this->travelTo(now()->subDays($daysAgo)->setTime(20, 0));

        $sprint = app(StartSprintAction::class)($user, [
            'mode' => 'pomodoro',
            'planned_duration_seconds' => $seconds,
            'break_seconds' => 300,
            'roadmap_item_id' => $item->id,
        ]);

        $this->travelTo(now()->addSeconds($seconds));

        app(CompleteSprintAction::class)($sprint);

        $this->travelBack();
    }

    protected function travelTo(\DateTimeInterface $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }

    protected function travelBack(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    protected function category(string $name): ?Category
    {
        return Category::query()->whereNull('user_id')->where('name', $name)->first();
    }
}
