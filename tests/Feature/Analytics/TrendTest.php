<?php

use App\Models\Goal;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * FR-ANL-01's heatmap calendar and FR-ANL-04's line chart.
 *
 * Both were gaps an audit found: the only per-day series in the API was
 * cross-goal and self-only, so a per-goal heatmap would have shown a member's
 * total activity on every goal's page, and a group "line" comparison had no
 * time dimension to draw at all.
 */
beforeEach(function () {
    $this->freezeTime();

    $this->owner = User::factory()->create(['timezone' => 'UTC']);
    $this->peer = User::factory()->create(['timezone' => 'UTC', 'name' => 'Peer']);

    $this->group = Group::factory()->create(['owner_id' => $this->owner->id]);
    GroupMember::factory()->create(['group_id' => $this->group->id, 'user_id' => $this->peer->id]);

    $this->goal = Goal::factory()->for($this->owner)->groupVisible()->create([
        'group_id' => $this->group->id,
    ]);
    $this->roadmap = Roadmap::factory()->for($this->goal)->create();
});

it('serves a per goal daily trend alongside the stats', function () {
    Sprint::factory()->for($this->owner)->completed(1800)->create([
        'goal_id' => $this->goal->id,
        'ended_at' => now(),
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/v1/goals/{$this->goal->id}/stats?trend_days=14")->assertOk();

    $trend = collect($response->json('daily_trend'));

    expect($trend)->toHaveCount(14)
        ->and($trend->last()['focus_minutes'])->toBe(30)
        ->and($trend->first()['focus_minutes'])->toBe(0);
});

/**
 * The reason this cannot reuse /analytics/overview: that endpoint has no goal
 * filter, so another goal's time would appear on this goal's heatmap.
 */
it('counts only this goal time in its own trend', function () {
    $otherGoal = Goal::factory()->for($this->owner)->create();

    Sprint::factory()->for($this->owner)->completed(600)->create([
        'goal_id' => $this->goal->id, 'ended_at' => now(),
    ]);
    Sprint::factory()->for($this->owner)->completed(9000)->create([
        'goal_id' => $otherGoal->id, 'ended_at' => now(),
    ]);

    Sanctum::actingAs($this->owner);

    $trend = collect($this->getJson("/api/v1/goals/{$this->goal->id}/stats")->assertOk()
        ->json('daily_trend'));

    expect($trend->last()['focus_minutes'])->toBe(10);
});

it('includes time logged against an item of the goal', function () {
    $item = RoadmapItem::factory()->for($this->roadmap)->create();

    Sprint::factory()->for($this->owner)->completed(1200)->create([
        'goal_id' => null,
        'roadmap_item_id' => $item->id,
        'ended_at' => now(),
    ]);

    Sanctum::actingAs($this->owner);

    $trend = collect($this->getJson("/api/v1/goals/{$this->goal->id}/stats")->assertOk()
        ->json('daily_trend'));

    expect($trend->last()['focus_minutes'])->toBe(20);
});

it('forbids the goal trend to a member who cannot view the goal', function () {
    $goal = Goal::factory()->for($this->owner)->create(['visibility' => 'private']);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/goals/{$goal->id}/stats")->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| FR-ANL-04 group trend
|--------------------------------------------------------------------------
*/

it('serves a per member series for the group', function () {
    Sprint::factory()->for($this->owner)->completed(1800)->create([
        'goal_id' => $this->goal->id, 'ended_at' => now(),
    ]);

    $peerGoal = Goal::factory()->for($this->peer)->groupVisible()->create([
        'group_id' => $this->group->id,
    ]);
    Sprint::factory()->for($this->peer)->completed(600)->create([
        'goal_id' => $peerGoal->id, 'ended_at' => now()->subDay(),
    ]);

    Sanctum::actingAs($this->owner);

    $data = collect($this->getJson("/api/v1/groups/{$this->group->id}/trend?trend_days=7")
        ->assertOk()->json('data'));

    expect($data)->toHaveCount(2);

    $peerSeries = collect($data->firstWhere('user.name', 'Peer')['series']);

    expect($peerSeries)->toHaveCount(7)
        ->and($peerSeries->last()['focus_minutes'])->toBe(0)
        ->and($peerSeries[5]['focus_minutes'])->toBe(10);
});

/**
 * Bounded by the same shared-goal rule as the leaderboard, so a private goal
 * can no more appear here than there (01 §5 Privacy).
 */
it('never counts a private goal in the group trend', function () {
    $private = Goal::factory()->for($this->peer)->create(['visibility' => 'private']);

    Sprint::factory()->for($this->peer)->completed(36000)->create([
        'goal_id' => $private->id, 'ended_at' => now(),
    ]);

    Sanctum::actingAs($this->owner);

    $series = collect($this->getJson("/api/v1/groups/{$this->group->id}/trend")->assertOk()
        ->json('data'))->firstWhere('user.name', 'Peer')['series'];

    expect(collect($series)->sum('focus_minutes'))->toBe(0);
});

it('forbids the group trend to a non member', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/groups/{$this->group->id}/trend")->assertForbidden();
});
