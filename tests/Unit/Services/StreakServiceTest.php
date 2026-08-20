<?php

use App\Models\ActivityLog;
use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use App\Services\StreakService;

beforeEach(function () {
    $this->streaks = app(StreakService::class);
});

/**
 * @return array{0: Goal, 1: RoadmapItem}
 */
function goalForStreaks(User $user): array
{
    $goal = Goal::factory()->for($user)->create();
    $roadmap = Roadmap::factory()->for($goal)->create();

    return [$goal, RoadmapItem::factory()->for($roadmap)->create()];
}

it('counts consecutive qualifying days', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    [$goal, $item] = goalForStreaks($user);

    foreach ([0, 1, 2, 3] as $daysAgo) {
        Sprint::factory()->for($user)->completed()->create([
            'goal_id' => $goal->id,
            'roadmap_item_id' => $item->id,
            'ended_at' => now()->subDays($daysAgo),
        ]);
    }

    expect($this->streaks->forGoal($goal))
        ->current->toBe(4)
        ->longest->toBe(4);
});

it('resets the current streak after a gap but remembers the longest', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    [$goal, $item] = goalForStreaks($user);

    /** A five-day run, then a four-day gap, then today. */
    foreach ([10, 9, 8, 7, 6, 0] as $daysAgo) {
        Sprint::factory()->for($user)->completed()->create([
            'goal_id' => $goal->id,
            'roadmap_item_id' => $item->id,
            'ended_at' => now()->subDays($daysAgo),
        ]);
    }

    expect($this->streaks->forGoal($goal))
        ->current->toBe(1)
        ->longest->toBe(5);
});

/**
 * A member who has not studied *yet today* has not broken anything. Ending
 * the streak at midnight would punish everyone who studies in the evening.
 */
it('keeps a streak alive when the last activity was yesterday', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    [$goal, $item] = goalForStreaks($user);

    foreach ([1, 2] as $daysAgo) {
        Sprint::factory()->for($user)->completed()->create([
            'goal_id' => $goal->id,
            'roadmap_item_id' => $item->id,
            'ended_at' => now()->subDays($daysAgo),
        ]);
    }

    expect($this->streaks->forGoal($goal)['current'])->toBe(2);
});

it('drops the streak to zero once two days have passed', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    [$goal, $item] = goalForStreaks($user);

    Sprint::factory()->for($user)->completed()->create([
        'goal_id' => $goal->id,
        'roadmap_item_id' => $item->id,
        'ended_at' => now()->subDays(3),
    ]);

    expect($this->streaks->forGoal($goal)['current'])->toBe(0);
});

/**
 * 06 §1.3 names this case: **two members in different timezones crossing the
 * day boundary at different UTC instants.**
 *
 * Both goals below get the *same two UTC instants*. In UTC they fall on one
 * calendar day; in Dhaka (UTC+6) the later one has already tipped past
 * midnight, so it is two days there. Grouping timestamps by their UTC date —
 * the obvious implementation — would report the same streak for both members
 * and be wrong for one of them.
 */
it('resolves the day boundary in each member own timezone', function () {
    $this->travelTo('2026-03-11 06:00:00');

    $utcUser = User::factory()->create(['timezone' => 'UTC']);
    $dhakaUser = User::factory()->create(['timezone' => 'Asia/Dhaka']);

    $instants = ['2026-03-10 17:00:00', '2026-03-10 20:00:00'];

    foreach ([$utcUser, $dhakaUser] as $user) {
        [$goal, $item] = goalForStreaks($user);

        foreach ($instants as $endedAt) {
            Sprint::factory()->for($user)->completed()->create([
                'goal_id' => $goal->id,
                'roadmap_item_id' => $item->id,
                'ended_at' => $endedAt,
            ]);
        }

        $user->streakResult = $this->streaks->forGoal($goal);
    }

    /** 17:00 and 20:00 UTC are both the 10th in UTC: one qualifying day. */
    expect($utcUser->streakResult['current'])->toBe(1);

    /** In Dhaka they are 23:00 on the 10th and 02:00 on the 11th: two days. */
    expect($dhakaUser->streakResult['current'])->toBe(2);
});

/**
 * FR-GAM-01 counts a completed Sprint *or* a Roadmap Item marked done.
 */
it('counts an item marked done as a qualifying day', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    [$goal, $item] = goalForStreaks($user);

    Sprint::factory()->for($user)->completed()->create([
        'goal_id' => $goal->id,
        'roadmap_item_id' => $item->id,
        'ended_at' => now()->subDays(1),
    ]);

    ActivityLog::factory()->create([
        'user_id' => $user->id,
        'subject_type' => RoadmapItem::class,
        'subject_id' => $item->id,
        'action' => 'roadmap_item.completed',
        'created_at' => now(),
    ]);

    expect($this->streaks->forGoal($goal)['current'])->toBe(2);
});

/**
 * Item completions are read from the activity feed rather than from
 * `roadmap_items.updated_at`, because a later edit to the row would move that
 * timestamp and invent a qualifying day.
 */
it('does not treat a later edit of a done item as fresh activity', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    [$goal, $item] = goalForStreaks($user);

    ActivityLog::factory()->create([
        'user_id' => $user->id,
        'subject_type' => RoadmapItem::class,
        'subject_id' => $item->id,
        'action' => 'roadmap_item.completed',
        'created_at' => now()->subDays(5),
    ]);

    /** The row is touched today, but nothing was actually completed today. */
    $item->forceFill(['status' => 'done'])->save();

    expect($this->streaks->forGoal($goal)['current'])->toBe(0);
});

it('reports a zero streak for a goal with no activity', function () {
    $user = User::factory()->create();
    [$goal] = goalForStreaks($user);

    expect($this->streaks->forGoal($goal))
        ->current->toBe(0)
        ->longest->toBe(0)
        ->and($this->streaks->forGoal($goal)['last_active_date'])->toBeNull();
});

it('counts several sprints on one day as a single qualifying day', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    [$goal, $item] = goalForStreaks($user);

    foreach ([1, 2, 3] as $hoursAgo) {
        Sprint::factory()->for($user)->completed()->create([
            'goal_id' => $goal->id,
            'roadmap_item_id' => $item->id,
            'ended_at' => now()->subHours($hoursAgo),
        ]);
    }

    expect($this->streaks->forGoal($goal)['current'])->toBe(1);
});
