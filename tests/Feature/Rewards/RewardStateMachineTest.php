<?php

use App\Models\Goal;
use App\Models\Mentorship;
use App\Models\Reward;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use App\Notifications\RewardClaimedNotification;
use App\Notifications\RewardFulfilledNotification;
use App\Notifications\RewardOfferedNotification;
use App\Notifications\RewardRequestRespondedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/**
 * FR-RWD-01..07 and the state diagram in 02 §3.
 *
 * 06 §1.2 asks for a dedicated test per transition proving the correct actor
 * can trigger it and every other actor gets 403, plus 422 for wrong-state
 * attempts. The two codes mean different things and both matter: **403 is
 * "not your move", 422 is "not yet"** — the actor check lives in RewardPolicy
 * and the state check in the Action, which is what keeps them distinguishable.
 */
beforeEach(function () {
    Notification::fake();
    Queue::fake();

    $this->mentor = User::factory()->create();
    $this->mentee = User::factory()->create();
    $this->stranger = User::factory()->create();

    $this->mentorship = Mentorship::factory()
        ->accepted()
        ->between($this->mentor, $this->mentee)
        ->create();

    $this->goal = Goal::factory()->for($this->mentee)->create();
    $roadmap = Roadmap::factory()->for($this->goal)->create();
    $this->item = RoadmapItem::factory()->for($roadmap)->create();
});

/**
 * @return array<string, mixed>
 */
function offerPayload(int $mentorshipId, int $itemId): array
{
    return [
        'mentorship_id' => $mentorshipId,
        'roadmap_item_id' => $itemId,
        'title' => '500 taka',
        'type' => 'monetary',
        'monetary_amount' => 500,
        'currency_label' => 'BDT',
    ];
}

/*
|--------------------------------------------------------------------------
| [*] -> offered : the mentor pre-commits
|--------------------------------------------------------------------------
*/

it('lets a mentor offer a reward', function () {
    Sanctum::actingAs($this->mentor);

    $this->postJson('/api/v1/rewards', offerPayload($this->mentorship->id, $this->item->id))
        ->assertCreated()
        ->assertJsonPath('data.status', 'offered')
        ->assertJsonPath('data.requested_by', 'mentor');

    Notification::assertSentTo($this->mentee, RewardOfferedNotification::class);
});

it('refuses to let a mentee offer a reward to themselves', function () {
    Sanctum::actingAs($this->mentee);

    $this->postJson('/api/v1/rewards', offerPayload($this->mentorship->id, $this->item->id))
        ->assertForbidden();

    expect(Reward::query()->count())->toBe(0);
});

it('refuses to let a stranger offer a reward', function () {
    Sanctum::actingAs($this->stranger);

    $this->postJson('/api/v1/rewards', offerPayload($this->mentorship->id, $this->item->id))
        ->assertForbidden();
});

/**
 * 02 §3: "you can't offer a reward outside an accepted mentorship."
 */
it('refuses an offer on a mentorship that is not accepted', function () {
    $this->mentorship->forceFill(['status' => 'ended'])->save();

    Sanctum::actingAs($this->mentor);

    $this->postJson('/api/v1/rewards', offerPayload($this->mentorship->id, $this->item->id))
        ->assertForbidden();
});

/**
 * 02 §3: "at least one of goal_id/roadmap_item_id", enforced in the Form
 * Request because a migration cannot express it cleanly.
 */
it('requires an offer to be anchored to a goal or an item', function () {
    Sanctum::actingAs($this->mentor);

    $this->postJson('/api/v1/rewards', [
        'mentorship_id' => $this->mentorship->id,
        'title' => 'Floating reward',
        'type' => 'custom',
    ])->assertUnprocessable()->assertJsonValidationErrors(['goal_id', 'roadmap_item_id']);
});

it('refuses an offer anchored to somebody else work', function () {
    $foreignItem = RoadmapItem::factory()->create();

    Sanctum::actingAs($this->mentor);

    $this->postJson('/api/v1/rewards', offerPayload($this->mentorship->id, $foreignItem->id))
        ->assertUnprocessable()->assertJsonValidationErrors(['roadmap_item_id']);
});

it('refuses an amount on a non monetary reward', function () {
    Sanctum::actingAs($this->mentor);

    $this->postJson('/api/v1/rewards', [
        'mentorship_id' => $this->mentorship->id,
        'roadmap_item_id' => $this->item->id,
        'title' => 'Movie night',
        'type' => 'privilege',
        'monetary_amount' => 500,
    ])->assertUnprocessable()->assertJsonValidationErrors(['monetary_amount']);
});

/*
|--------------------------------------------------------------------------
| [*] -> requested : the mentee asks for something not pre-offered
|--------------------------------------------------------------------------
*/

it('lets a mentee request a reward that was never offered', function () {
    Sanctum::actingAs($this->mentee);

    $this->postJson('/api/v1/rewards/request', [
        'mentorship_id' => $this->mentorship->id,
        'title' => 'Extra screen time',
        'type' => 'privilege',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'requested')
        ->assertJsonPath('data.requested_by', 'mentee');

    Notification::assertSentTo($this->mentor, RewardOfferedNotification::class);
});

it('refuses to let a mentor request on the mentee behalf', function () {
    Sanctum::actingAs($this->mentor);

    $this->postJson('/api/v1/rewards/request', [
        'mentorship_id' => $this->mentorship->id,
        'title' => 'Extra screen time',
        'type' => 'privilege',
    ])->assertForbidden();
});

/**
 * Unlike an offer, a request need not be anchored yet — it is a question, not
 * a commitment.
 */
it('allows a request with no linked goal or item', function () {
    Sanctum::actingAs($this->mentee);

    $this->postJson('/api/v1/rewards/request', [
        'mentorship_id' => $this->mentorship->id,
        'title' => 'A new keyboard',
        'type' => 'custom',
    ])->assertCreated();
});

/*
|--------------------------------------------------------------------------
| requested -> offered | denied
|--------------------------------------------------------------------------
*/

it('lets a mentor accept a requested reward', function () {
    $reward = Reward::factory()->requested()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentor);

    $this->postJson("/api/v1/rewards/{$reward->id}/respond", ['accepted' => true])
        ->assertOk()
        ->assertJsonPath('data.status', 'offered');

    Notification::assertSentTo($this->mentee, RewardRequestRespondedNotification::class);
});

it('lets a mentor deny a requested reward with a note', function () {
    $reward = Reward::factory()->requested()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentor);

    $this->postJson("/api/v1/rewards/{$reward->id}/respond", [
        'accepted' => false,
        'note' => 'Maybe after the exams.',
    ])->assertOk()->assertJsonPath('data.status', 'denied');
});

it('refuses to let the mentee respond to their own request', function () {
    $reward = Reward::factory()->requested()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentee);

    $this->postJson("/api/v1/rewards/{$reward->id}/respond", ['accepted' => true])
        ->assertForbidden();

    expect($reward->fresh()->status)->toBe('requested');
});

it('refuses to let a stranger respond', function () {
    $reward = Reward::factory()->requested()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->stranger);

    $this->postJson("/api/v1/rewards/{$reward->id}/respond", ['accepted' => true])
        ->assertForbidden();
});

it('rejects responding to a reward that was not requested', function () {
    $reward = Reward::factory()->offered()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentor);

    $this->postJson("/api/v1/rewards/{$reward->id}/respond", ['accepted' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

/*
|--------------------------------------------------------------------------
| earned -> claimed
|--------------------------------------------------------------------------
*/

it('lets a mentee claim an earned reward', function () {
    $reward = Reward::factory()->earned()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentee);

    $this->postJson("/api/v1/rewards/{$reward->id}/claim")
        ->assertOk()
        ->assertJsonPath('data.status', 'claimed');

    expect($reward->fresh()->claimed_at)->not->toBeNull();

    Notification::assertSentTo($this->mentor, RewardClaimedNotification::class);
});

it('refuses to let a mentor claim on the mentee behalf', function () {
    $reward = Reward::factory()->earned()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentor);

    $this->postJson("/api/v1/rewards/{$reward->id}/claim")->assertForbidden();

    expect($reward->fresh()->status)->toBe('earned');
});

it('refuses to let a stranger claim', function () {
    $reward = Reward::factory()->earned()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->stranger);

    $this->postJson("/api/v1/rewards/{$reward->id}/claim")->assertForbidden();
});

/**
 * The wrong-state case 06 §1.2 names explicitly: the right actor, the wrong
 * moment — 422, not 403.
 */
it('rejects claiming a reward that is merely offered', function () {
    $reward = Reward::factory()->offered()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentee);

    $this->postJson("/api/v1/rewards/{$reward->id}/claim")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('rejects claiming the same reward twice', function () {
    $reward = Reward::factory()->claimed()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentee);

    $this->postJson("/api/v1/rewards/{$reward->id}/claim")->assertUnprocessable();
});

/*
|--------------------------------------------------------------------------
| claimed -> fulfilled
|--------------------------------------------------------------------------
*/

it('lets a mentor fulfill a claimed reward', function () {
    $reward = Reward::factory()->claimed()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentor);

    $this->postJson("/api/v1/rewards/{$reward->id}/fulfill", ['note' => 'Paid in cash, Aug 20'])
        ->assertOk()
        ->assertJsonPath('data.status', 'fulfilled')
        ->assertJsonPath('data.fulfilled_note', 'Paid in cash, Aug 20');

    expect($reward->fresh()->fulfilled_at)->not->toBeNull();

    Notification::assertSentTo($this->mentee, RewardFulfilledNotification::class);
});

it('refuses to let a mentee fulfill their own reward', function () {
    $reward = Reward::factory()->claimed()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentee);

    $this->postJson("/api/v1/rewards/{$reward->id}/fulfill")->assertForbidden();

    expect($reward->fresh()->status)->toBe('claimed');
});

it('rejects fulfilling a reward that was never claimed', function () {
    $reward = Reward::factory()->earned()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentor);

    $this->postJson("/api/v1/rewards/{$reward->id}/fulfill")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

/*
|--------------------------------------------------------------------------
| offered -> revoked
|--------------------------------------------------------------------------
*/

it('lets a mentor revoke an offer that has not been earned', function () {
    $reward = Reward::factory()->offered()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentor);

    $this->postJson("/api/v1/rewards/{$reward->id}/revoke", ['note' => 'Changed the plan.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'revoked');
});

/**
 * **The one that protects the mentee.** Revoking after the work is done would
 * let a mentor renege after the fact and make every offer worthless.
 */
it('rejects revoking a reward that has already been earned', function () {
    $reward = Reward::factory()->earned()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentor);

    $this->postJson("/api/v1/rewards/{$reward->id}/revoke")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    expect($reward->fresh()->status)->toBe('earned');
});

it('rejects revoking a claimed or fulfilled reward', function () {
    $claimed = Reward::factory()->claimed()->create(['mentorship_id' => $this->mentorship->id]);
    $fulfilled = Reward::factory()->fulfilled()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentor);

    $this->postJson("/api/v1/rewards/{$claimed->id}/revoke")->assertUnprocessable();
    $this->postJson("/api/v1/rewards/{$fulfilled->id}/revoke")->assertUnprocessable();
});

it('refuses to let a mentee revoke', function () {
    $reward = Reward::factory()->offered()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentee);

    $this->postJson("/api/v1/rewards/{$reward->id}/revoke")->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Listing and visibility
|--------------------------------------------------------------------------
*/

it('lists rewards for both sides and never for a stranger', function () {
    Reward::factory()->offered()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentor);
    $this->getJson('/api/v1/rewards')->assertOk()->assertJsonCount(1, 'data');

    Sanctum::actingAs($this->mentee);
    $this->getJson('/api/v1/rewards')->assertOk()->assertJsonCount(1, 'data');

    Sanctum::actingAs($this->stranger);
    $this->getJson('/api/v1/rewards')->assertOk()->assertJsonCount(0, 'data');
});

/**
 * The UI cannot offer a button the API would refuse, and cannot hide one it
 * would have allowed (03 §2.2 RewardCard).
 */
it('reports only the actions the viewer may actually take', function () {
    $earned = Reward::factory()->earned()->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentee);
    $this->getJson('/api/v1/rewards?status=earned')
        ->assertOk()
        ->assertJsonPath('data.0.viewer_role', 'mentee')
        ->assertJsonPath('data.0.available_actions', ['claim']);

    Sanctum::actingAs($this->mentor);
    $this->getJson('/api/v1/rewards?status=earned')
        ->assertOk()
        ->assertJsonPath('data.0.viewer_role', 'mentor')
        ->assertJsonPath('data.0.available_actions', []);
});

it('offers no actions once the mentorship has ended', function () {
    Reward::factory()->earned()->create(['mentorship_id' => $this->mentorship->id]);
    $this->mentorship->forceFill(['status' => 'ended'])->save();

    Sanctum::actingAs($this->mentee);

    $this->getJson('/api/v1/rewards')
        ->assertOk()
        ->assertJsonPath('data.0.available_actions', []);
});

it('requires authentication for every reward route', function () {
    $reward = Reward::factory()->earned()->create(['mentorship_id' => $this->mentorship->id]);

    app('auth')->forgetGuards();

    $this->getJson('/api/v1/rewards')->assertUnauthorized();
    $this->postJson("/api/v1/rewards/{$reward->id}/claim")->assertUnauthorized();
});
