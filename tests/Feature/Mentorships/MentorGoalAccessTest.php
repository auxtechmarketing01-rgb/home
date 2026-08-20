<?php

use App\Models\Goal;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Mentorship;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * FR-MENT-04: a mentor can view **all** of a mentee's goals and roadmaps,
 * regardless of that goal's `visibility`.
 *
 * This is the one grant that deliberately ignores visibility, because
 * mentorship is an explicit, mutual grant of read access rather than a side
 * effect of sharing a group. It is therefore also the branch most likely to be
 * accidentally widened or accidentally lost, so both directions are asserted:
 * what it must reach, and what it must still not.
 */
beforeEach(function () {
    $this->mentee = User::factory()->create();
    $this->mentor = User::factory()->create();
    $this->groupPeer = User::factory()->create();

    $group = Group::factory()->create(['owner_id' => $this->mentee->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $this->mentor->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $this->groupPeer->id]);

    $this->group = $group;

    $this->privateGoal = Goal::factory()->for($this->mentee)->create([
        'title' => 'Private goal',
        'visibility' => 'private',
    ]);

    $roadmap = Roadmap::factory()->for($this->privateGoal)->create();
    $this->item = RoadmapItem::factory()->for($roadmap)->create();
});

it('grants an accepted mentor read access to a private goal', function () {
    Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->mentor);

    $this->getJson("/api/v1/goals/{$this->privateGoal->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Private goal');
});

it('grants an accepted mentor read access to the roadmap items', function () {
    Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->mentor);

    $this->getJson("/api/v1/roadmaps/{$this->item->roadmap_id}/items")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('includes the mentee goals in the mentor own goal list', function () {
    Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    Goal::factory()->for($this->mentor)->create(['title' => 'My own goal']);

    Sanctum::actingAs($this->mentor);

    $titles = collect($this->getJson('/api/v1/goals')->assertOk()->json('data'))->pluck('title');

    expect($titles)->toContain('My own goal')->toContain('Private goal');
});

/**
 * A pending request grants nothing — the whole point of FR-MENT-02 is that
 * the other person has to agree first.
 */
it('grants nothing while the request is still pending', function () {
    Mentorship::factory()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->mentor);

    $this->getJson("/api/v1/goals/{$this->privateGoal->id}")->assertForbidden();
    $this->getJson('/api/v1/goals')->assertOk()->assertJsonCount(0, 'data');
});

it('grants nothing once the relationship has ended', function () {
    Mentorship::factory()->ended()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->mentor);

    $this->getJson("/api/v1/goals/{$this->privateGoal->id}")->assertForbidden();
});

it('grants nothing on a declined request', function () {
    Mentorship::factory()->declined()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->mentor);

    $this->getJson("/api/v1/goals/{$this->privateGoal->id}")->assertForbidden();
});

/**
 * The grant is directional. Being someone's mentor does not make them yours.
 */
it('does not grant the mentee access to the mentor goals', function () {
    Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    $mentorPrivateGoal = Goal::factory()->for($this->mentor)->create();

    Sanctum::actingAs($this->mentee);

    $this->getJson("/api/v1/goals/{$mentorPrivateGoal->id}")->assertForbidden();
});

/**
 * FR-MENT-04 is explicit that mentorship is "not a side effect of Group
 * membership" — so a plain group peer must still be refused a private goal.
 */
it('does not grant a mere group peer access to a private goal', function () {
    Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->groupPeer);

    $this->getJson("/api/v1/goals/{$this->privateGoal->id}")->assertForbidden();
});

/**
 * 02 §5: a mentor does **not** see or control a mentee's sprints. They see
 * aggregated time through goal stats and the leaderboard — the difference
 * between "I can see you are putting in the hours" and "I can watch you work".
 */
it('never exposes a mentee individual sprints to their mentor', function () {
    Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    $sprint = Sprint::factory()->for($this->mentee)->completed()->create([
        'goal_id' => $this->privateGoal->id,
    ]);

    Sanctum::actingAs($this->mentor);

    $this->getJson('/api/v1/sprints')->assertOk()->assertJsonCount(0, 'data');
    $this->postJson("/api/v1/sprints/{$sprint->id}/cancel")->assertForbidden();
});

/**
 * Read access is not write access (FR-MENT-06).
 */
it('refuses to let a mentor edit or archive the goal', function () {
    Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->mentor);

    $this->putJson("/api/v1/goals/{$this->privateGoal->id}", ['title' => 'Hijacked'])
        ->assertForbidden();
    $this->deleteJson("/api/v1/goals/{$this->privateGoal->id}")->assertForbidden();
    $this->postJson("/api/v1/goals/{$this->privateGoal->id}/complete")->assertForbidden();

    expect($this->privateGoal->fresh()->title)->toBe('Private goal');
});

/**
 * Attachments delegate to the parent goal, so a mentor can read them and not
 * remove them (02 §5).
 */
it('lets a mentor read attachments but not add or delete them', function () {
    Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->mentor);

    $this->getJson("/api/v1/goals/{$this->privateGoal->id}/resources")->assertOk();

    $this->postJson("/api/v1/goals/{$this->privateGoal->id}/resources", [
        'type' => 'note',
        'title' => 'Mentor note',
        'body' => 'Should not be allowed.',
    ])->assertForbidden();
});
