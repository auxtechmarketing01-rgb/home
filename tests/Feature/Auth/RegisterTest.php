<?php

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Support\Facades\Notification;

/**
 * FR-AUTH-01.
 */
it('registers a user and returns the profile', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/register', [
        'name' => 'Nasir',
        'email' => 'nasir@example.test',
        'password' => 'password-with-length',
        'password_confirmation' => 'password-with-length',
        'timezone' => 'Asia/Dhaka',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'nasir@example.test')
        ->assertJsonPath('data.timezone', 'Asia/Dhaka')
        ->assertJsonMissingPath('data.password');

    $user = User::query()->where('email', 'nasir@example.test')->sole();

    expect($user->level)->toBe(1)
        ->and($user->xp)->toBe(0);

    $this->assertAuthenticatedAs($user);

    Notification::assertSentTo($user, QueuedVerifyEmail::class);
});

it('rejects an invalid registration payload', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('rejects a duplicate email address', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    $response = $this->postJson('/api/v1/register', [
        'name' => 'Someone Else',
        'email' => 'taken@example.test',
        'password' => 'password-with-length',
        'password_confirmation' => 'password-with-length',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('never accepts xp or level from the registration payload', function () {
    $this->postJson('/api/v1/register', [
        'name' => 'Nasir',
        'email' => 'xp@example.test',
        'password' => 'password-with-length',
        'password_confirmation' => 'password-with-length',
        'xp' => 99999,
        'level' => 50,
    ])->assertCreated();

    $user = User::query()->where('email', 'xp@example.test')->sole();

    expect($user->xp)->toBe(0)
        ->and($user->level)->toBe(1);
});
