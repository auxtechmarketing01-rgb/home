<?php

use App\Jobs\RecalculateGoalStatsJob;
use App\Models\Goal;
use App\Models\Mentorship;
use App\Models\Reward;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use App\Notifications\RewardEarnedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * FR-RWD-02, asserted "via the job, not an endpoint" as 06 §1.2 requires —
 * **no HTTP request is involved anywhere in this file**. The flip is supposed
 * to happen because work got done, not because somebody called a URL.
 */
beforeEach(function () {
    Notification::fake();

    $this->mentor = User::factory()->create();
    $this->mentee = User::factory()->create();

    $this->mentorship = Mentorship::factory()
        ->accepted()
        ->between($this->mentor, $this->mentee)
        ->create();

    $this->goal = Goal::factory()->for($this->mentee)->create();
    $this->roadmap = Roadmap::factory()->for($this->goal)->create();
});

function runRecalculation(Goal $goal): void
{
    app()->call([new RecalculateGoalStatsJob($goal), 'handle']);
}

it('flips an offered reward to earned when its item is done', function () {
    $item = RoadmapItem::factory()->for($this->roadmap)->done()->create();

    $reward = Reward::factory()->offered()->create([
        'mentorship_id' => $this->mentorship->id,
        'roadmap_item_id' => $item->id,
    ]);

    runRecalculation($this->goal);

    expect($reward->fresh()->status)->toBe('earned');

    Notification::assertSentTo($this->mentee, RewardEarnedNotification::class);
});

/**
 * A reward on an item that is not finished must be untouched, even when a
 * sibling item on the same goal is done.
 */
it('leaves a reward on a still unfinished item alone', function () {
    $doneItem = RoadmapItem::factory()->for($this->roadmap)->done()->create();
    $openItem = RoadmapItem::factory()->for($this->roadmap)->create();

    $earnedReward = Reward::factory()->offered()->create([
        'mentorship_id' => $this->mentorship->id,
        'roadmap_item_id' => $doneItem->id,
    ]);

    $untouchedReward = Reward::factory()->offered()->create([
        'mentorship_id' => $this->mentorship->id,
        'roadmap_item_id' => $openItem->id,
    ]);

    runRecalculation($this->goal);

    expect($earnedReward->fresh()->status)->toBe('earned')
        ->and($untouchedReward->fresh()->status)->toBe('offered');
});

/**
 * FR-RWD-03: nothing was promised, so finishing the work cannot conjure a
 * promise. A `requested` reward stays `requested` until a mentor answers.
 */
it('never flips a requested reward even when its item is done', function () {
    $item = RoadmapItem::factory()->for($this->roadmap)->done()->create();

    $reward = Reward::factory()->requested()->create([
        'mentorship_id' => $this->mentorship->id,
        'roadmap_item_id' => $item->id,
    ]);

    runRecalculation($this->goal);

    expect($reward->fresh()->status)->toBe('requested');

    Notification::assertNothingSent();
});

it('never flips a revoked or denied reward', function () {
    $item = RoadmapItem::factory()->for($this->roadmap)->done()->create();

    $revoked = Reward::factory()->revoked()->create([
        'mentorship_id' => $this->mentorship->id,
        'roadmap_item_id' => $item->id,
    ]);

    $denied = Reward::factory()->denied()->create([
        'mentorship_id' => $this->mentorship->id,
        'roadmap_item_id' => $item->id,
    ]);

    runRecalculation($this->goal);

    expect($revoked->fresh()->status)->toBe('revoked')
        ->and($denied->fresh()->status)->toBe('denied');
});

/**
 * A goal-level reward lands only when the *goal* is complete — not when any
 * one of its items happens to be.
 */
it('flips a goal level reward only once the goal itself is complete', function () {
    RoadmapItem::factory()->for($this->roadmap)->done()->create();

    $reward = Reward::factory()->offered()->create([
        'mentorship_id' => $this->mentorship->id,
        'goal_id' => $this->goal->id,
        'roadmap_item_id' => null,
    ]);

    runRecalculation($this->goal);
    expect($reward->fresh()->status)->toBe('offered');

    $this->goal->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

    runRecalculation($this->goal->fresh());
    expect($reward->fresh()->status)->toBe('earned');
});

/**
 * The job is debounced and re-runs freely, so a second pass must not send a
 * second notification for the same event.
 */
it('is idempotent and notifies exactly once', function () {
    $item = RoadmapItem::factory()->for($this->roadmap)->done()->create();

    Reward::factory()->offered()->create([
        'mentorship_id' => $this->mentorship->id,
        'roadmap_item_id' => $item->id,
    ]);

    runRecalculation($this->goal);
    runRecalculation($this->goal);
    runRecalculation($this->goal);

    Notification::assertSentToTimes($this->mentee, RewardEarnedNotification::class, 1);
});

it('never touches a reward belonging to another goal', function () {
    $otherGoal = Goal::factory()->for($this->mentee)->create();
    $otherRoadmap = Roadmap::factory()->for($otherGoal)->create();
    $otherItem = RoadmapItem::factory()->for($otherRoadmap)->done()->create();

    $reward = Reward::factory()->offered()->create([
        'mentorship_id' => $this->mentorship->id,
        'roadmap_item_id' => $otherItem->id,
    ]);

    runRecalculation($this->goal);

    expect($reward->fresh()->status)->toBe('offered');
});

/**
 * `earned` is a status flip and nothing more. Delivery stays a human step —
 * this is the "auto-crediting is the wrong default" finding from 01 §2.
 */
it('pays nothing out and leaves the claim step to the mentee', function () {
    $item = RoadmapItem::factory()->for($this->roadmap)->done()->create();

    $reward = Reward::factory()->offered()->monetary(500, 'BDT')->create([
        'mentorship_id' => $this->mentorship->id,
        'roadmap_item_id' => $item->id,
    ]);

    runRecalculation($this->goal);

    $reward->refresh();

    expect($reward->status)->toBe('earned')
        ->and($reward->claimed_at)->toBeNull()
        ->and($reward->fulfilled_at)->toBeNull()
        ->and((float) $reward->monetary_amount)->toBe(500.0);
});
