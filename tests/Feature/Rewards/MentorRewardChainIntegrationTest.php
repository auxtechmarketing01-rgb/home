<?php

use App\Models\Goal;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Mentorship;
use App\Models\Reward;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * 06 §3, gate 5 — the second of the two non-negotiable integration checks,
 * run deliberately **without `Queue::fake()`**.
 *
 * This walks the entire chain added in Phases 3 and 4 in one pass: shared
 * group -> mentorship request -> acceptance -> reward offer -> the mentee
 * finishing the work -> the automatic `earned` flip -> claim -> fulfil.
 *
 * Every other reward test starts from a factory state. This one starts from
 * two strangers in a group and only uses the API, so nothing in the middle of
 * the chain can be quietly broken while the unit tests stay green — in
 * particular the `RecalculateGoalStatsJob` -> `MarkRewardsEarnedForItemAction`
 * link, which no HTTP endpoint triggers directly.
 */
it('runs the whole mentor and reward chain end to end', function () {
    $mentor = User::factory()->create(['name' => 'Older sibling']);
    $mentee = User::factory()->create(['name' => 'Younger sibling']);

    /** They know each other through a group — there is no public directory. */
    $group = Group::factory()->create(['owner_id' => $mentor->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $mentee->id]);

    /* 1. The mentee asks the older sibling to mentor them. */
    Sanctum::actingAs($mentee);

    $mentorshipId = $this->postJson('/api/v1/mentorships', [
        'user_id' => $mentor->id,
        'role' => 'mentor',
    ])->assertCreated()->json('data.id');

    /* 2. Only the other party can accept. */
    Sanctum::actingAs($mentor);
    $this->postJson("/api/v1/mentorships/{$mentorshipId}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    /* 3. The mentee builds a goal with one roadmap item. */
    Sanctum::actingAs($mentee);

    $goalId = $this->postJson('/api/v1/goals', ['title' => 'Learn C Programming'])
        ->assertCreated()->json('data.id');

    $roadmapId = Goal::query()->findOrFail($goalId)->roadmap->id;

    $itemId = $this->postJson("/api/v1/roadmaps/{$roadmapId}/items", [
        'title' => 'Day 1 – Variables',
        'estimated_minutes' => 90,
    ])->assertCreated()->json('data.id');

    /* 4. The mentor sets an expectation, and offers a reward for the item. */
    Sanctum::actingAs($mentor);

    $this->patchJson("/api/v1/roadmap-items/{$itemId}/assign", ['assigned_minutes' => 120])
        ->assertOk()
        ->assertJsonPath('data.assigned_minutes', 120);

    $rewardId = $this->postJson('/api/v1/rewards', [
        'mentorship_id' => $mentorshipId,
        'roadmap_item_id' => $itemId,
        'title' => '500 taka',
        'type' => 'monetary',
        'monetary_amount' => 500,
        'currency_label' => 'BDT',
    ])->assertCreated()->assertJsonPath('data.status', 'offered')->json('data.id');

    /* 5. The mentee does the work and marks the item done. */
    Sanctum::actingAs($mentee);

    $this->putJson("/api/v1/roadmap-items/{$itemId}", ['status' => 'done'])->assertOk();

    /*
     * 6. The flip happened with no HTTP request behind it — the queued job
     *    ran and MarkRewardsEarnedForItemAction did the work. This is the
     *    assertion that would fail if the job/action link were broken while
     *    every unit test still passed.
     */
    expect(Reward::query()->findOrFail($rewardId)->status)->toBe('earned');

    $this->getJson('/api/v1/rewards?status=earned')
        ->assertOk()
        ->assertJsonPath('data.0.id', $rewardId)
        ->assertJsonPath('data.0.available_actions', ['claim']);

    /* 7. The mentee claims it. Nothing auto-credits. */
    $this->postJson("/api/v1/rewards/{$rewardId}/claim")
        ->assertOk()
        ->assertJsonPath('data.status', 'claimed');

    /* 8. The mentor records that they actually delivered it. */
    Sanctum::actingAs($mentor);

    $this->postJson("/api/v1/rewards/{$rewardId}/fulfill", ['note' => 'Paid in cash, Aug 20'])
        ->assertOk()
        ->assertJsonPath('data.status', 'fulfilled');

    /* 9. And it shows up in the ledger — a record, never a balance. */
    $this->getJson('/api/v1/rewards/ledger')
        ->assertOk()
        ->assertJsonPath('data.0.fulfilled_count', 1)
        ->assertJsonPath('data.0.totals_by_label.BDT', '500');

    /*
     * 10. The goal stats were rebuilt along the way, by the same job.
     *     Asserted as `100` rather than `100.0` because JSON has a single
     *     number type and a whole float decodes as an int.
     */
    $this->getJson("/api/v1/goals/{$goalId}/stats")
        ->assertOk()
        ->assertJsonPath('data.completion_percentage', 100);
});

/**
 * The same chain, checked from the other direction: ending the relationship
 * afterwards must not rewrite what already happened (FR-MENT-07).
 */
it('keeps a fulfilled reward after the mentorship ends', function () {
    $mentor = User::factory()->create();
    $mentee = User::factory()->create();

    $group = Group::factory()->create(['owner_id' => $mentor->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $mentee->id]);

    $mentorship = Mentorship::factory()->accepted()->between($mentor, $mentee)->create();

    $goal = Goal::factory()->for($mentee)->create();
    $roadmap = Roadmap::factory()->for($goal)->create();
    $item = RoadmapItem::factory()->for($roadmap)->create();

    $reward = Reward::factory()->offered()->monetary(300, 'BDT')->create([
        'mentorship_id' => $mentorship->id,
        'roadmap_item_id' => $item->id,
    ]);

    Sanctum::actingAs($mentee);
    $this->putJson("/api/v1/roadmap-items/{$item->id}", ['status' => 'done'])->assertOk();

    expect($reward->fresh()->status)->toBe('earned');

    $this->postJson("/api/v1/rewards/{$reward->id}/claim")->assertOk();

    Sanctum::actingAs($mentor);
    $this->postJson("/api/v1/rewards/{$reward->id}/fulfill")->assertOk();

    Sanctum::actingAs($mentee);
    $this->postJson("/api/v1/mentorships/{$mentorship->id}/end")->assertOk();

    expect($reward->fresh()->status)->toBe('fulfilled');

    /** But the mentor's read access is gone going forward. */
    Sanctum::actingAs($mentor);
    $this->getJson("/api/v1/goals/{$goal->id}")->assertForbidden();
});
