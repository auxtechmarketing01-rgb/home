<?php

use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\Goal;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Notifications\ChallengeUpdateNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/**
 * FR-GRP-04 (Squad Challenge). Distinct from the leaderboard: the leaderboard
 * is passive and includes everyone, a challenge is opt-in and goal-specific.
 */
beforeEach(function () {
    Notification::fake();

    $this->owner = User::factory()->create(['name' => 'Amina']);
    $this->peer = User::factory()->create(['name' => 'Bilal']);
    $this->outsider = User::factory()->create();

    $this->group = Group::factory()->create(['owner_id' => $this->owner->id]);
    GroupMember::factory()->create(['group_id' => $this->group->id, 'user_id' => $this->peer->id]);
});

it('lets any group member create a challenge and enrols the creator', function () {
    $goal = Goal::factory()->for($this->peer)->create();

    Sanctum::actingAs($this->peer);

    $this->postJson("/api/v1/groups/{$this->group->id}/challenges", [
        'title' => 'Race to finish C',
        'goal_id' => $goal->id,
    ])->assertCreated()
        ->assertJsonPath('data.title', 'Race to finish C')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.participants_count', 1)
        ->assertJsonPath('data.has_joined', true);

    expect(ChallengeParticipant::query()->sole()->user_id)->toBe($this->peer->id);
});

it('refuses to let an outsider create or list challenges', function () {
    Sanctum::actingAs($this->outsider);

    $this->postJson("/api/v1/groups/{$this->group->id}/challenges", ['title' => 'Intruder'])
        ->assertForbidden();
    $this->getJson("/api/v1/groups/{$this->group->id}/challenges")->assertForbidden();

    expect(Challenge::query()->count())->toBe(0);
});

/**
 * Racing with somebody else's goal would put their progress on your row.
 */
it('refuses a goal that is not the creator own', function () {
    $foreignGoal = Goal::factory()->for($this->owner)->create();

    Sanctum::actingAs($this->peer);

    $this->postJson("/api/v1/groups/{$this->group->id}/challenges", [
        'title' => 'Race',
        'goal_id' => $foreignGoal->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['goal_id']);
});

it('lets another member join with their own goal', function () {
    $challenge = Challenge::factory()->create([
        'group_id' => $this->group->id,
        'created_by' => $this->owner->id,
    ]);
    ChallengeParticipant::factory()->create([
        'challenge_id' => $challenge->id,
        'user_id' => $this->owner->id,
    ]);

    $goal = Goal::factory()->for($this->peer)->create();

    Sanctum::actingAs($this->peer);

    $this->postJson("/api/v1/challenges/{$challenge->id}/join", ['goal_id' => $goal->id])
        ->assertOk()
        ->assertJsonPath('data.participants_count', 2);

    /** The social pull: the others hear about it, the joiner does not. */
    Notification::assertSentTo($this->owner, ChallengeUpdateNotification::class);
    Notification::assertNotSentTo($this->peer, ChallengeUpdateNotification::class);
});

it('refuses to join twice', function () {
    $challenge = Challenge::factory()->create([
        'group_id' => $this->group->id,
        'created_by' => $this->owner->id,
    ]);
    ChallengeParticipant::factory()->create([
        'challenge_id' => $challenge->id,
        'user_id' => $this->peer->id,
    ]);

    Sanctum::actingAs($this->peer);

    $this->postJson("/api/v1/challenges/{$challenge->id}/join")->assertUnprocessable();

    expect($challenge->participants()->count())->toBe(1);
});

it('refuses to let an outsider join or view', function () {
    $challenge = Challenge::factory()->create([
        'group_id' => $this->group->id,
        'created_by' => $this->owner->id,
    ]);

    Sanctum::actingAs($this->outsider);

    $this->getJson("/api/v1/challenges/{$challenge->id}")->assertForbidden();
    $this->postJson("/api/v1/challenges/{$challenge->id}/join")->assertForbidden();
});

it('refuses to join a challenge that is no longer active', function () {
    $challenge = Challenge::factory()->completed()->create([
        'group_id' => $this->group->id,
        'created_by' => $this->owner->id,
    ]);

    Sanctum::actingAs($this->peer);

    $this->postJson("/api/v1/challenges/{$challenge->id}/join")->assertForbidden();
});

it('shows each participant progress from the stats cache', function () {
    $challenge = Challenge::factory()->create([
        'group_id' => $this->group->id,
        'created_by' => $this->owner->id,
    ]);

    $goal = Goal::factory()->for($this->owner)->create(['title' => 'Learn C']);
    $goal->stats()->create();
    $goal->stats->forceFill([
        'total_focus_seconds' => 7200,
        'completion_percentage' => 40,
    ])->save();

    ChallengeParticipant::factory()->create([
        'challenge_id' => $challenge->id,
        'user_id' => $this->owner->id,
        'goal_id' => $goal->id,
    ]);

    Sanctum::actingAs($this->peer);

    $this->getJson("/api/v1/challenges/{$challenge->id}")
        ->assertOk()
        ->assertJsonPath('data.participants.0.user.name', 'Amina')
        ->assertJsonPath('data.participants.0.goal.title', 'Learn C')
        ->assertJsonPath('data.participants.0.goal.total_focus_seconds', 7200)
        ->assertJsonPath('data.participants.0.goal.completion_percentage', 40);
});

it('lets a participant leave', function () {
    $challenge = Challenge::factory()->create([
        'group_id' => $this->group->id,
        'created_by' => $this->owner->id,
    ]);
    ChallengeParticipant::factory()->create([
        'challenge_id' => $challenge->id,
        'user_id' => $this->peer->id,
    ]);

    Sanctum::actingAs($this->peer);

    $this->postJson("/api/v1/challenges/{$challenge->id}/leave")->assertNoContent();

    expect($challenge->participants()->count())->toBe(0);
});

it('refuses to leave a challenge you never joined', function () {
    $challenge = Challenge::factory()->create([
        'group_id' => $this->group->id,
        'created_by' => $this->owner->id,
    ]);

    Sanctum::actingAs($this->peer);

    $this->postJson("/api/v1/challenges/{$challenge->id}/leave")->assertUnprocessable();
});

/**
 * Closing a challenge belongs to whoever started it, or to the group owner as
 * a backstop for an abandoned one.
 */
it('lets the creator or the group owner delete it and nobody else', function () {
    $challenge = Challenge::factory()->create([
        'group_id' => $this->group->id,
        'created_by' => $this->peer->id,
    ]);

    $bystander = User::factory()->create();
    GroupMember::factory()->create(['group_id' => $this->group->id, 'user_id' => $bystander->id]);

    Sanctum::actingAs($bystander);
    $this->deleteJson("/api/v1/challenges/{$challenge->id}")->assertForbidden();

    Sanctum::actingAs($this->owner);
    $this->deleteJson("/api/v1/challenges/{$challenge->id}")->assertNoContent();
});

it('rejects an end date before the start date', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/groups/{$this->group->id}/challenges", [
        'title' => 'Race',
        'starts_on' => '2026-05-01',
        'ends_on' => '2026-04-01',
    ])->assertUnprocessable()->assertJsonValidationErrors(['ends_on']);
});
