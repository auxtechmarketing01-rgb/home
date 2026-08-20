<?php

use App\Models\Sprint;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/**
 * 02 §5: sprints are owner-only, full stop. Not group-visible, and a mentor
 * gets nothing here either — mentors see focus time only as aggregates,
 * through goal stats and the leaderboard.
 */
beforeEach(function () {
    Queue::fake();

    $this->owner = User::factory()->create();
    $this->sprint = Sprint::factory()->for($this->owner)->running()->create();

    Sanctum::actingAs(User::factory()->create());
});

it('forbids a stranger from pausing another member sprint', function () {
    $this->postJson("/api/v1/sprints/{$this->sprint->id}/pause")->assertForbidden();

    expect($this->sprint->fresh()->status)->toBe('running');
});

it('forbids a stranger from resuming another member sprint', function () {
    $paused = Sprint::factory()->for($this->owner)->paused()->create();

    $this->postJson("/api/v1/sprints/{$paused->id}/resume")->assertForbidden();

    expect($paused->fresh()->status)->toBe('paused');
});

it('forbids a stranger from completing another member sprint', function () {
    $this->postJson("/api/v1/sprints/{$this->sprint->id}/complete")->assertForbidden();

    expect($this->sprint->fresh()->actual_duration_seconds)->toBeNull();
});

it('forbids a stranger from cancelling another member sprint', function () {
    $this->postJson("/api/v1/sprints/{$this->sprint->id}/cancel")->assertForbidden();

    expect($this->sprint->fresh()->status)->toBe('running');
});

/**
 * `/sprints` carries no Policy because it is scoped to the acting member
 * (02 §4) — an empty Policy column means self-scoped, never unscoped. This
 * asserts the scoping actually happens.
 */
it('never lists another member sprints', function () {
    $this->getJson('/api/v1/sprints')->assertOk()->assertJsonCount(0, 'data');
});

it('never exposes another member active sprint', function () {
    $this->getJson('/api/v1/sprints/active')->assertOk()->assertJsonPath('data', null);
});

it('never exports another member sprints', function () {
    $response = $this->get('/api/v1/sprints/export')->assertOk();

    expect($response->streamedContent())->not->toContain((string) $this->sprint->id.',');
});

it('requires authentication', function () {
    app('auth')->forgetGuards();

    $this->getJson('/api/v1/sprints')->assertUnauthorized();
    $this->postJson('/api/v1/sprints/start', ['mode' => 'stopwatch'])->assertUnauthorized();
});
