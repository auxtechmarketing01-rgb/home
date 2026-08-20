<?php

use App\Models\User;

/**
 * This test authenticates through the session guard rather than
 * Sanctum::actingAs on purpose: cookie SPA auth *is* the session guard
 * (02 §1), and logging out is only observable when the guard that holds the
 * session is the one being cleared.
 */
it('logs the user out and clears the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/logout')
        ->assertOk();

    /**
     * Asserted against the `web` guard specifically rather than through
     * assertGuest(): `auth:sanctum` makes sanctum the default guard for the
     * rest of the request and Sanctum's RequestGuard caches the user it
     * resolved, so the default guard can still report a user after the
     * session behind it has been thrown away. The session guard is the one
     * that actually holds SPA cookie auth.
     */
    expect(auth('web')->check())->toBeFalse();
});

it('returns 401 from /user when unauthenticated', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});

it('returns the authenticated profile from /user', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Dhaka']);

    $this->actingAs($user)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.timezone', 'Asia/Dhaka')
        ->assertJsonMissingPath('data.password');
});
