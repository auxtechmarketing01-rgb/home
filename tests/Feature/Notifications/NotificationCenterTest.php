<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\TestMemberNotification;

/**
 * FR-NOT-01. 02 §4 marks these endpoints "scoped to self" rather than
 * policy-checked, so the query always starts from the acting user's own
 * relation.
 */
it('lists only the acting member notifications', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $user->notify(new TestMemberNotification('Yours.'));
    $other->notify(new TestMemberNotification('Not yours.'));

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/notifications')->assertOk();

    $response->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.payload.message', 'Yours.')
        ->assertJsonPath('data.0.type', 'TestMemberNotification')
        ->assertJsonPath('unread_count', 1);
});

it('filters to unread notifications', function () {
    $user = User::factory()->create();

    $user->notify(new TestMemberNotification('First.'));
    $user->notify(new TestMemberNotification('Second.'));

    $user->notifications()->first()->markAsRead();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/notifications?unread=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('unread_count', 1);
});

it('marks a notification read', function () {
    $user = User::factory()->create();
    $user->notify(new TestMemberNotification);

    $notification = $user->notifications()->sole();

    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.id', $notification->id);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

/**
 * A self-scoped endpoint resolves nothing for another member's id, so this is
 * a 404 rather than the 403 the policy-guarded routes return (02 §4).
 */
it('cannot mark another member notification read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $other->notify(new TestMemberNotification);
    $notification = $other->notifications()->sole();

    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/notifications/{$notification->id}/read")->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('requires authentication', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
});

/**
 * The durable record is written even though `broadcast` is in the same
 * `via()` list — a member must never depend on having had a tab open.
 */
it('persists the notification centre record alongside the broadcast', function () {
    $user = User::factory()->create();

    $user->notify(new TestMemberNotification('Reward earned.'));

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $user->id,
        'notifiable_type' => User::class,
        'type' => TestMemberNotification::class,
        'read_at' => null,
    ]);
});
