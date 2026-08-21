<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * FR-AUTH-02.
 */
it('logs a user in with correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'member@example.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'member@example.test',
        'password' => 'correct-horse-battery',
    ]);

    $response->assertOk()->assertJsonPath('data.id', $user->id);

    $this->assertAuthenticatedAs($user);
});

it('rejects incorrect credentials', function () {
    User::factory()->create([
        'email' => 'member@example.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->postJson('/api/v1/login', [
        'email' => 'member@example.test',
        'password' => 'wrong-password',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    $this->assertGuest();
});

/**
 * Cookie SPA auth needs a session, and Sanctum only starts one for a request
 * it recognises as first-party (an `Origin`/`Referer` matching
 * SANCTUM_STATEFUL_DOMAINS).
 *
 * Found by driving the real server with curl rather than the test client:
 * without the guard in AuthController, a request from an unconfigured origin
 * reached `$request->session()` and died with an unhandled
 * "Session store not set on request" **500**. That reports a configuration
 * problem as a server fault and tells the operator nothing — the same
 * "deliberate response, not an unhandled 500" principle the sprint conflict
 * follows (02 §1).
 */
it('answers a request from an unrecognised origin with a clear error rather than a 500', function () {
    User::factory()->create([
        'email' => 'member@example.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    /** Drop the first-party Referer the base TestCase adds. */
    $response = $this->withoutMiddleware(
        EnsureFrontendRequestsAreStateful::class
    )->postJson('/api/v1/login', [
        'email' => 'member@example.test',
        'password' => 'correct-horse-battery',
    ]);

    $response->assertStatus(400);

    expect($response->json('message'))->toContain('SANCTUM_STATEFUL_DOMAINS');
});

/**
 * 01 NFR Security / 04 Cross-cutting: `/login` is rate limited.
 */
it('rate limits repeated failed login attempts', function () {
    User::factory()->create([
        'email' => 'member@example.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $payload = [
        'email' => 'member@example.test',
        'password' => 'wrong-password',
    ];

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/api/v1/login', $payload)->assertUnprocessable();
    }

    $this->postJson('/api/v1/login', $payload)->assertStatus(429);
});
