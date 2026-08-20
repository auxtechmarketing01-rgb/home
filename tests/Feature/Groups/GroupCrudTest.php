<?php

use App\Models\Goal;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Notifications\GroupInviteNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/**
 * FR-GRP-01 and FR-GRP-05.
 */
beforeEach(function () {
    Notification::fake();

    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->outsider = User::factory()->create();
});

it('creates a group with the creator as owner and member', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/groups', ['name' => 'The Rahmans'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'The Rahmans')
        ->assertJsonPath('data.is_owner', true)
        ->assertJsonPath('data.members_count', 1);

    $group = Group::query()->sole();

    expect($group->owner_id)->toBe($this->owner->id)
        ->and($group->invite_code)->not->toBeNull()
        ->and($group->hasMember($this->owner))->toBeTrue();
});

/**
 * A group with no membership row for its owner would be invisible to the
 * owner, since GroupPolicy::view is membership-based.
 */
it('makes a new group immediately visible to its creator', function () {
    Sanctum::actingAs($this->owner);

    $groupId = $this->postJson('/api/v1/groups', ['name' => 'The Rahmans'])->json('data.id');

    $this->getJson("/api/v1/groups/{$groupId}")->assertOk();
    $this->getJson('/api/v1/groups')->assertOk()->assertJsonCount(1, 'data');
});

it('lists only groups the member belongs to', function () {
    Group::factory()->create(['owner_id' => $this->owner->id, 'name' => 'Mine']);
    Group::factory()->create(['owner_id' => $this->outsider->id, 'name' => 'Not mine']);

    Sanctum::actingAs($this->owner);

    $this->getJson('/api/v1/groups')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Mine');
});

it('joins a group with a valid invite code', function () {
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);

    Sanctum::actingAs($this->member);

    $this->postJson('/api/v1/groups/join', ['invite_code' => $group->invite_code])
        ->assertCreated()
        ->assertJsonPath('data.id', $group->id);

    expect($group->fresh()->hasMember($this->member))->toBeTrue();
});

it('rejects an invalid invite code', function () {
    Sanctum::actingAs($this->member);

    $this->postJson('/api/v1/groups/join', ['invite_code' => 'NOPE123456'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['invite_code']);
});

it('rejects joining a group twice', function () {
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);

    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/groups/join', ['invite_code' => $group->invite_code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['invite_code']);

    expect($group->memberships()->count())->toBe(1);
});

/**
 * FR-GRP-01: the invite code is the credential that grants entry, so only the
 * owner ever sees it. A member who could read it could invite strangers into a
 * circle that may include minors.
 */
it('shows the invite code to the owner and hides it from members', function () {
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $this->member->id]);

    Sanctum::actingAs($this->owner);
    $this->getJson("/api/v1/groups/{$group->id}")
        ->assertOk()
        ->assertJsonPath('data.invite_code', $group->invite_code);

    Sanctum::actingAs($this->member);
    $this->getJson("/api/v1/groups/{$group->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.invite_code');
});

it('notifies an existing member when invited by email', function () {
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/groups/{$group->id}/invite", ['email' => $this->member->email])
        ->assertOk()
        ->assertJsonPath('notified', true)
        ->assertJsonPath('invite_code', $group->invite_code);

    Notification::assertSentTo($this->member, GroupInviteNotification::class);
});

/**
 * There is no public directory, so an unknown address cannot be notified —
 * but the owner still gets the code back to pass along, which is the only way
 * to invite a family member who has not signed up yet.
 */
it('returns the code without notifying when the address has no account', function () {
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/groups/{$group->id}/invite", ['email' => 'nobody@example.test'])
        ->assertOk()
        ->assertJsonPath('notified', false)
        ->assertJsonPath('invite_code', $group->invite_code);

    Notification::assertNothingSent();
});

it('rejects inviting somebody who is already a member', function () {
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $this->member->id]);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/groups/{$group->id}/invite", ['email' => $this->member->email])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

/**
 * FR-GRP-01: regenerating is how an owner invalidates a code that has been
 * shared too widely.
 */
it('regenerates the invite code and invalidates the old one', function () {
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);
    $oldCode = $group->invite_code;

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/groups/{$group->id}/invite-code")->assertOk();

    expect($group->fresh()->invite_code)->not->toBe($oldCode);

    Sanctum::actingAs($this->outsider);
    $this->postJson('/api/v1/groups/join', ['invite_code' => $oldCode])->assertUnprocessable();
});

it('renames a group', function () {
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);

    Sanctum::actingAs($this->owner);

    $this->putJson("/api/v1/groups/{$group->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');
});

/**
 * FR-GRP-05.
 */
it('lets the owner remove a member', function () {
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $this->member->id]);

    Sanctum::actingAs($this->owner);

    $this->deleteJson("/api/v1/groups/{$group->id}/members/{$this->member->id}")->assertNoContent();

    expect($group->fresh()->hasMember($this->member))->toBeFalse();
});

it('refuses to remove the owner', function () {
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);

    Sanctum::actingAs($this->owner);

    $this->deleteJson("/api/v1/groups/{$group->id}/members/{$this->owner->id}")
        ->assertUnprocessable();

    expect($group->fresh()->hasMember($this->owner))->toBeTrue();
});

it('lets a member leave but not the owner', function () {
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $this->member->id]);

    Sanctum::actingAs($this->member);
    $this->postJson("/api/v1/groups/{$group->id}/leave")->assertNoContent();

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/groups/{$group->id}/leave")->assertForbidden();
});

/**
 * A departing member keeps their goals, but those goals must stop being
 * visible to a group they are no longer in (01 §5 Privacy).
 */
it('detaches a departing member goals from the group', function () {
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $this->member->id]);

    $goal = Goal::factory()->for($this->member)->groupVisible()->create(['group_id' => $group->id]);

    Sanctum::actingAs($this->member);
    $this->postJson("/api/v1/groups/{$group->id}/leave")->assertNoContent();

    expect($goal->fresh()->group_id)->toBeNull();

    /** And the remaining member can no longer see it. */
    Sanctum::actingAs($this->owner);
    $this->getJson("/api/v1/goals/{$goal->id}")->assertForbidden();
});

it('deletes a group as the owner only', function () {
    $group = Group::factory()->create(['owner_id' => $this->owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $this->member->id]);

    Sanctum::actingAs($this->member);
    $this->deleteJson("/api/v1/groups/{$group->id}")->assertForbidden();

    Sanctum::actingAs($this->owner);
    $this->deleteJson("/api/v1/groups/{$group->id}")->assertNoContent();

    $this->assertDatabaseMissing('groups', ['id' => $group->id]);
});
