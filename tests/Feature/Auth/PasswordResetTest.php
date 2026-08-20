<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * FR-AUTH-03.
 */
it('sends a reset link for a known address', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'member@example.test']);

    $this->postJson('/api/v1/forgot-password', ['email' => 'member@example.test'])
        ->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('does not reveal whether an address exists', function () {
    Notification::fake();

    $this->postJson('/api/v1/forgot-password', ['email' => 'nobody@example.test'])
        ->assertOk();

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'member@example.test',
        'password' => Hash::make('old-password-value'),
    ]);

    $this->postJson('/api/v1/forgot-password', ['email' => 'member@example.test'])->assertOk();

    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->postJson('/api/v1/reset-password', [
        'token' => $token,
        'email' => 'member@example.test',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertOk();

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();
});

/**
 * The broker marks the token used, so a second attempt with the same token
 * must fail.
 */
it('rejects a token that has already been used', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'member@example.test']);

    $this->postJson('/api/v1/forgot-password', ['email' => 'member@example.test'])->assertOk();

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $payload = [
        'token' => $token,
        'email' => 'member@example.test',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ];

    $this->postJson('/api/v1/reset-password', $payload)->assertOk();
    $this->postJson('/api/v1/reset-password', $payload)->assertUnprocessable();
});

it('rejects an invalid token', function () {
    User::factory()->create(['email' => 'member@example.test']);

    $this->postJson('/api/v1/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'member@example.test',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});
