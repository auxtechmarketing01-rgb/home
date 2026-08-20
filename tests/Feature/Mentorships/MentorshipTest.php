<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Mentorship;
use App\Models\Reward;
use App\Models\User;
use App\Notifications\MentorshipAcceptedNotification;
use App\Notifications\MentorshipRequestedNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/**
 * FR-MENT-01..07.
 */
beforeEach(function () {
    Notification::fake();

    $this->mentee = User::factory()->create(['name' => 'Younger sibling']);
    $this->mentor = User::factory()->create(['name' => 'Older sibling']);
    $this->outsider = User::factory()->create();

    $this->group = Group::factory()->create(['owner_id' => $this->mentee->id]);
    GroupMember::factory()->create(['group_id' => $this->group->id, 'user_id' => $this->mentor->id]);
});

/**
 * FR-MENT-01. The gate that matters: no public directory, so a request is
 * only possible toward someone you already share a group with — and the app
 * may include minors.
 */
it('requires a shared group', function () {
    Sanctum::actingAs($this->mentee);

    $this->postJson('/api/v1/mentorships', [
        'user_id' => $this->outsider->id,
        'role' => 'mentor',
    ])->assertForbidden();

    expect(Mentorship::query()->count())->toBe(0);
});

it('creates a pending request toward someone in a shared group', function () {
    Sanctum::actingAs($this->mentee);

    $this->postJson('/api/v1/mentorships', [
        'user_id' => $this->mentor->id,
        'role' => 'mentor',
    ])->assertCreated()->assertJsonPath('data.status', 'pending');

    $mentorship = Mentorship::query()->sole();

    expect($mentorship->mentor_id)->toBe($this->mentor->id)
        ->and($mentorship->mentee_id)->toBe($this->mentee->id)
        ->and($mentorship->requested_by_user_id)->toBe($this->mentee->id)
        ->and($mentorship->responded_at)->toBeNull();

    Notification::assertSentTo($this->mentor, MentorshipRequestedNotification::class);
});

/**
 * Either party may initiate (02 §3 `requested_by_user_id`), so a mentor can
 * offer rather than only be asked. `role` describes the *other* person.
 */
it('lets a prospective mentor initiate', function () {
    Sanctum::actingAs($this->mentor);

    $this->postJson('/api/v1/mentorships', [
        'user_id' => $this->mentee->id,
        'role' => 'mentee',
    ])->assertCreated();

    $mentorship = Mentorship::query()->sole();

    expect($mentorship->mentor_id)->toBe($this->mentor->id)
        ->and($mentorship->mentee_id)->toBe($this->mentee->id)
        ->and($mentorship->requested_by_user_id)->toBe($this->mentor->id);

    Notification::assertSentTo($this->mentee, MentorshipRequestedNotification::class);
});

it('refuses a request to mentor yourself', function () {
    Sanctum::actingAs($this->mentee);

    $this->postJson('/api/v1/mentorships', [
        'user_id' => $this->mentee->id,
        'role' => 'mentor',
    ])->assertUnprocessable()->assertJsonValidationErrors(['user_id']);
});

/**
 * **FR-MENT-02, the mistake worth guarding hardest.** A requester who could
 * accept their own request would hand themselves read access to every one of
 * the other member's goals with nobody agreeing to it.
 */
it('refuses to let the requester accept their own request', function () {
    $mentorship = Mentorship::factory()
        ->between($this->mentor, $this->mentee)
        ->requestedBy($this->mentee)
        ->create();

    Sanctum::actingAs($this->mentee);

    $this->postJson("/api/v1/mentorships/{$mentorship->id}/accept")->assertForbidden();

    expect($mentorship->fresh()->status)->toBe('pending');
});

it('lets the non initiating party accept', function () {
    $mentorship = Mentorship::factory()
        ->between($this->mentor, $this->mentee)
        ->requestedBy($this->mentee)
        ->create();

    Sanctum::actingAs($this->mentor);

    $this->postJson("/api/v1/mentorships/{$mentorship->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect($mentorship->fresh()->responded_at)->not->toBeNull();

    Notification::assertSentTo($this->mentee, MentorshipAcceptedNotification::class);
});

it('lets the non initiating party decline', function () {
    $mentorship = Mentorship::factory()
        ->between($this->mentor, $this->mentee)
        ->requestedBy($this->mentee)
        ->create();

    Sanctum::actingAs($this->mentor);

    $this->postJson("/api/v1/mentorships/{$mentorship->id}/decline")
        ->assertOk()
        ->assertJsonPath('data.status', 'declined');
});

it('refuses to answer a request twice', function () {
    $mentorship = Mentorship::factory()
        ->accepted()
        ->between($this->mentor, $this->mentee)
        ->requestedBy($this->mentee)
        ->create();

    Sanctum::actingAs($this->mentor);

    $this->postJson("/api/v1/mentorships/{$mentorship->id}/accept")->assertForbidden();
});

it('refuses an unrelated member from responding', function () {
    $mentorship = Mentorship::factory()
        ->between($this->mentor, $this->mentee)
        ->requestedBy($this->mentee)
        ->create();

    Sanctum::actingAs($this->outsider);

    $this->postJson("/api/v1/mentorships/{$mentorship->id}/accept")->assertForbidden();
});

/**
 * 02 §3: a pair re-requesting after `ended` reuses its row, so the history of
 * two people stays in one place and the unique constraint is never fought.
 */
it('reuses the existing row when a pair re requests after ending', function () {
    $original = Mentorship::factory()
        ->ended()
        ->between($this->mentor, $this->mentee)
        ->create();

    Sanctum::actingAs($this->mentee);

    $this->postJson('/api/v1/mentorships', [
        'user_id' => $this->mentor->id,
        'role' => 'mentor',
    ])->assertCreated()->assertJsonPath('data.id', $original->id);

    expect(Mentorship::query()->count())->toBe(1)
        ->and($original->fresh()->status)->toBe('pending')
        ->and($original->fresh()->responded_at)->toBeNull();
});

it('refuses a duplicate request while one is already pending', function () {
    Mentorship::factory()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->mentee);

    $this->postJson('/api/v1/mentorships', [
        'user_id' => $this->mentor->id,
        'role' => 'mentor',
    ])->assertUnprocessable()->assertJsonValidationErrors(['user_id']);

    expect(Mentorship::query()->count())->toBe(1);
});

it('refuses a duplicate request when one is already active', function () {
    Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->mentee);

    $this->postJson('/api/v1/mentorships', [
        'user_id' => $this->mentor->id,
        'role' => 'mentor',
    ])->assertUnprocessable();
});

/**
 * FR-MENT-03: many mentors and many mentees at once, and the two directions
 * between the same pair are separate rows.
 */
it('allows both directions between the same pair', function () {
    Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->mentor);

    $this->postJson('/api/v1/mentorships', [
        'user_id' => $this->mentee->id,
        'role' => 'mentor',
    ])->assertCreated();

    expect(Mentorship::query()->count())->toBe(2);
});

/**
 * FR-MENT-07: either party, at any time.
 */
it('lets either party end the relationship', function () {
    $mentorship = Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->mentee);
    $this->postJson("/api/v1/mentorships/{$mentorship->id}/end")
        ->assertOk()
        ->assertJsonPath('data.status', 'ended');

    $second = Mentorship::factory()->accepted()->between($this->mentee, $this->mentor)->create();

    Sanctum::actingAs($this->mentee);
    $this->postJson("/api/v1/mentorships/{$second->id}/end")->assertOk();
});

it('refuses an outsider from ending a relationship', function () {
    $mentorship = Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->outsider);

    $this->postJson("/api/v1/mentorships/{$mentorship->id}/end")->assertForbidden();

    expect($mentorship->fresh()->status)->toBe('accepted');
});

/**
 * FR-MENT-07 explicitly: ending does not retroactively revoke rewards
 * already fulfilled. The ledger records things that happened outside the app,
 * and rewriting it would be falsifying history.
 */
it('leaves already fulfilled rewards intact when the relationship ends', function () {
    $mentorship = Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    $fulfilled = Reward::factory()->fulfilled()->monetary(500, 'BDT')->create([
        'mentorship_id' => $mentorship->id,
    ]);

    Sanctum::actingAs($this->mentee);
    $this->postJson("/api/v1/mentorships/{$mentorship->id}/end")->assertOk();

    $fulfilled->refresh();

    expect($fulfilled->status)->toBe('fulfilled')
        ->and($fulfilled->fulfilled_at)->not->toBeNull()
        ->and((float) $fulfilled->monetary_amount)->toBe(500.0);
});

it('lists relationships on both sides and filters by role', function () {
    Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();
    Mentorship::factory()->accepted()->between($this->mentee, $this->outsider)->create();

    Sanctum::actingAs($this->mentee);

    $this->getJson('/api/v1/mentorships')->assertOk()->assertJsonCount(2, 'data');

    $this->getJson('/api/v1/mentorships?role=mentee')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.viewer_role', 'mentee');

    $this->getJson('/api/v1/mentorships?role=mentor')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.viewer_role', 'mentor');
});

it('never lists another member relationships', function () {
    Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    Sanctum::actingAs($this->outsider);

    $this->getJson('/api/v1/mentorships')->assertOk()->assertJsonCount(0, 'data');
});
