<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * FR-SPR-10's registration endpoints, and the boundary 06 §1.2 names: a
 * subscription must bind to the authenticated member and must not be
 * aimable at somebody else's account.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

function pushSubscriptionPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
{
    return [
        'endpoint' => $endpoint,
        'keys' => [
            'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM=',
            'auth' => 'tBHItJI5svbpez7KI4CCXg==',
        ],
        'contentEncoding' => 'aes128gcm',
    ];
}

it('stores a subscription bound to the authenticated member', function () {
    $this->postJson('/api/v1/push-subscriptions', pushSubscriptionPayload())->assertCreated();

    $this->assertDatabaseHas('push_subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        'subscribable_id' => $this->user->id,
        'subscribable_type' => User::class,
    ]);
});

/**
 * There is no user field in the payload at all, so this is structurally
 * impossible rather than merely validated — assert it stays that way.
 */
it('cannot be aimed at another member account', function () {
    $other = User::factory()->create();

    $this->postJson('/api/v1/push-subscriptions', [
        ...pushSubscriptionPayload(),
        'user_id' => $other->id,
        'subscribable_id' => $other->id,
    ])->assertCreated();

    $this->assertDatabaseHas('push_subscriptions', [
        'subscribable_id' => $this->user->id,
    ]);

    $this->assertDatabaseMissing('push_subscriptions', [
        'subscribable_id' => $other->id,
    ]);
});

it('rejects a malformed subscription payload', function () {
    $this->postJson('/api/v1/push-subscriptions', ['endpoint' => 'https://example.test/push'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['keys']);

    $this->postJson('/api/v1/push-subscriptions', [
        'endpoint' => 'https://example.test/push',
        'keys' => ['auth' => 'only-auth'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['keys.p256dh']);

    $this->postJson('/api/v1/push-subscriptions', pushSubscriptionPayload(''))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint']);
});

it('removes a subscription when the member turns notifications off', function () {
    $this->postJson('/api/v1/push-subscriptions', pushSubscriptionPayload())->assertCreated();

    $this->deleteJson('/api/v1/push-subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
    ])->assertNoContent();

    $this->assertDatabaseMissing('push_subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
    ]);
});

it('does not remove another member subscription with the same endpoint', function () {
    $other = User::factory()->create();
    $other->updatePushSubscription('https://shared.test/endpoint', 'key', 'token');

    $this->postJson('/api/v1/push-subscriptions', pushSubscriptionPayload('https://mine.test/endpoint'))
        ->assertCreated();

    $this->deleteJson('/api/v1/push-subscriptions', ['endpoint' => 'https://shared.test/endpoint'])
        ->assertNoContent();

    $this->assertDatabaseHas('push_subscriptions', [
        'endpoint' => 'https://shared.test/endpoint',
        'subscribable_id' => $other->id,
    ]);
});

it('requires authentication', function () {
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/push-subscriptions', pushSubscriptionPayload())->assertUnauthorized();
});
