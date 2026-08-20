<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * FR-AUTH-04. `timezone` matters beyond display: it decides where this
 * user's day boundary falls for streak math.
 */
it('updates name and timezone', function () {
    $user = User::factory()->create(['name' => 'Old Name', 'timezone' => 'UTC']);
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/user', [
        'name' => 'New Name',
        'timezone' => 'Asia/Dhaka',
    ])->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.timezone', 'Asia/Dhaka');

    expect($user->fresh()->timezone)->toBe('Asia/Dhaka');
});

it('rejects a timezone that is not a real identifier', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/v1/user', ['timezone' => 'Mars/Olympus_Mons'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['timezone']);
});

it('stores an uploaded avatar on the configured disk', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/user', [
        'avatar' => UploadedFile::fake()->image('me.png'),
    ])->assertOk();

    $path = $user->fresh()->avatar_path;

    expect($path)->not->toBeNull();
    Storage::disk('local')->assertExists($path);
});

/**
 * FR-GAM-02: gamification is toggleable off per user.
 */
it('merges settings rather than replacing them', function () {
    $user = User::factory()->create(['settings' => ['reminder_hour' => 21]]);
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/user', [
        'settings' => ['gamification_enabled' => false],
    ])->assertOk()->assertJsonPath('data.gamification_enabled', false);

    expect($user->fresh()->settings)->toBe([
        'reminder_hour' => 21,
        'gamification_enabled' => false,
    ]);
});

it('never lets a user raise their own xp or level through the profile endpoint', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/user', ['xp' => 5000, 'level' => 40])->assertOk();

    expect($user->fresh()->xp)->toBe(0)
        ->and($user->fresh()->level)->toBe(1);
});
