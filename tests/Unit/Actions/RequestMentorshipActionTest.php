<?php

use App\Actions\Mentorships\RequestMentorshipAction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Mentorship;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * 06 §1.3 names this Action for direct testing, and an audit found it had none.
 *
 * The reason it matters: FR-MENT-01's shared-group requirement is real
 * authorization, not input validation. There is no public directory and the app
 * may include minors, so this is the boundary that stops a stranger reaching a
 * child — and it has to hold when the Action is called from a console command
 * or a future client that never passes through RequestMentorshipRequest.
 */
beforeEach(function () {
    Notification::fake();

    $this->actor = User::factory()->create();
    $this->peer = User::factory()->create();
    $this->stranger = User::factory()->create();

    $group = Group::factory()->create(['owner_id' => $this->actor->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $this->peer->id]);

    $this->action = app(RequestMentorshipAction::class);
});

it('refuses a target with no shared group even with no form request in front of it', function () {
    expect(fn () => ($this->action)($this->actor, $this->stranger, 'mentor'))
        ->toThrow(AuthorizationException::class);

    expect(Mentorship::query()->count())->toBe(0);
});

it('creates a pending request toward a shared group member', function () {
    $mentorship = ($this->action)($this->actor, $this->peer, 'mentor');

    expect($mentorship->status)->toBe('pending')
        ->and($mentorship->mentor_id)->toBe($this->peer->id)
        ->and($mentorship->mentee_id)->toBe($this->actor->id)
        ->and($mentorship->requested_by_user_id)->toBe($this->actor->id)
        ->and($mentorship->responded_at)->toBeNull();
});

/**
 * `role` describes the *other* person, which is what lets one entry point
 * serve both directions (02 §3 `requested_by_user_id`).
 */
it('flips the sides when the actor is offering to mentor', function () {
    $mentorship = ($this->action)($this->actor, $this->peer, 'mentee');

    expect($mentorship->mentor_id)->toBe($this->actor->id)
        ->and($mentorship->mentee_id)->toBe($this->peer->id)
        ->and($mentorship->requested_by_user_id)->toBe($this->actor->id);
});

it('refuses a second request while one is pending', function () {
    ($this->action)($this->actor, $this->peer, 'mentor');

    expect(fn () => ($this->action)($this->actor, $this->peer, 'mentor'))
        ->toThrow(ValidationException::class);

    expect(Mentorship::query()->count())->toBe(1);
});

it('refuses a request when the pair is already active', function () {
    Mentorship::factory()->accepted()->between($this->peer, $this->actor)->create();

    expect(fn () => ($this->action)($this->actor, $this->peer, 'mentor'))
        ->toThrow(ValidationException::class);
});

/**
 * 02 §3: a pair re-requesting after `ended` reuses its row, so the history of
 * two people stays in one place and the unique constraint is never fought.
 */
it('reuses the row after an ended relationship rather than inserting a duplicate', function () {
    $original = Mentorship::factory()->ended()->between($this->peer, $this->actor)->create();

    $again = ($this->action)($this->actor, $this->peer, 'mentor');

    expect($again->id)->toBe($original->id)
        ->and($again->status)->toBe('pending')
        ->and($again->responded_at)->toBeNull()
        ->and(Mentorship::query()->count())->toBe(1);
});

it('reuses the row after a declined request', function () {
    $original = Mentorship::factory()->declined()->between($this->peer, $this->actor)->create();

    expect(($this->action)($this->actor, $this->peer, 'mentor')->id)->toBe($original->id)
        ->and(Mentorship::query()->count())->toBe(1);
});

it('records who initiated so only the other party can respond', function () {
    $mentorship = ($this->action)($this->actor, $this->peer, 'mentor');

    expect($mentorship->isInitiator($this->actor))->toBeTrue()
        ->and($mentorship->isInitiator($this->peer))->toBeFalse();
});

it('refuses to let a member request themselves', function () {
    expect(fn () => ($this->action)($this->actor, $this->actor, 'mentor'))
        ->toThrow(AuthorizationException::class);
});
