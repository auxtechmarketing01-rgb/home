<?php

use App\Models\Mentorship;
use App\Models\Reward;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * FR-RWD-06: a running per-mentee-per-mentor ledger of **fulfilled monetary**
 * rewards, so a parent does not have to remember what they still owe.
 *
 * The critical property is what it is *not*: a wallet. Nothing here is a
 * spendable balance, and only rewards actually delivered appear — an `earned`
 * or `claimed` reward is still a promise, and listing it would read as a debt
 * already settled (01 NFR Financial integrity).
 */
beforeEach(function () {
    $this->mentor = User::factory()->create(['name' => 'Parent']);
    $this->mentee = User::factory()->create(['name' => 'Child']);
    $this->stranger = User::factory()->create();

    $this->mentorship = Mentorship::factory()
        ->accepted()
        ->between($this->mentor, $this->mentee)
        ->create();
});

it('sums fulfilled monetary rewards per relationship', function () {
    Reward::factory()->fulfilled()->monetary(500, 'BDT')->create(['mentorship_id' => $this->mentorship->id]);
    Reward::factory()->fulfilled()->monetary(300, 'BDT')->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentor);

    $this->getJson('/api/v1/rewards/ledger')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.fulfilled_count', 2)
        ->assertJsonPath('data.0.totals_by_label.BDT', '800')
        ->assertJsonPath('data.0.mentor.name', 'Parent')
        ->assertJsonPath('data.0.mentee.name', 'Child');
});

/**
 * Only delivered money counts. This is the assertion that keeps the ledger
 * honest.
 */
it('excludes rewards that were promised but not delivered', function () {
    Reward::factory()->offered()->monetary(100, 'BDT')->create(['mentorship_id' => $this->mentorship->id]);
    Reward::factory()->earned()->monetary(200, 'BDT')->create(['mentorship_id' => $this->mentorship->id]);
    Reward::factory()->claimed()->monetary(400, 'BDT')->create(['mentorship_id' => $this->mentorship->id]);
    Reward::factory()->fulfilled()->monetary(50, 'BDT')->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentor);

    $this->getJson('/api/v1/rewards/ledger')
        ->assertOk()
        ->assertJsonPath('data.0.fulfilled_count', 1)
        ->assertJsonPath('data.0.totals_by_label.BDT', '50');
});

it('excludes non monetary rewards', function () {
    Reward::factory()->fulfilled()->create([
        'mentorship_id' => $this->mentorship->id,
        'type' => 'privilege',
    ]);

    Sanctum::actingAs($this->mentor);

    $this->getJson('/api/v1/rewards/ledger')->assertOk()->assertJsonCount(0, 'data');
});

/**
 * `currency_label` is free text on purpose (02 §3), so adding "500 BDT" to
 * "20 USD" would produce a meaningless number. The totals stay grouped.
 */
it('keeps different currency labels apart instead of summing them', function () {
    Reward::factory()->fulfilled()->monetary(500, 'BDT')->create(['mentorship_id' => $this->mentorship->id]);
    Reward::factory()->fulfilled()->monetary(20, 'USD')->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentor);

    $this->getJson('/api/v1/rewards/ledger')
        ->assertOk()
        ->assertJsonPath('data.0.totals_by_label.BDT', '500')
        ->assertJsonPath('data.0.totals_by_label.USD', '20');
});

it('is visible to both parties and to nobody else', function () {
    Reward::factory()->fulfilled()->monetary(500, 'BDT')->create(['mentorship_id' => $this->mentorship->id]);

    Sanctum::actingAs($this->mentee);
    $this->getJson('/api/v1/rewards/ledger')->assertOk()->assertJsonCount(1, 'data');

    Sanctum::actingAs($this->stranger);
    $this->getJson('/api/v1/rewards/ledger')->assertOk()->assertJsonCount(0, 'data');
});

/**
 * FR-MENT-07: the record survives the relationship ending.
 */
it('still reports rewards from a relationship that has ended', function () {
    Reward::factory()->fulfilled()->monetary(500, 'BDT')->create(['mentorship_id' => $this->mentorship->id]);
    $this->mentorship->forceFill(['status' => 'ended'])->save();

    Sanctum::actingAs($this->mentor);

    $this->getJson('/api/v1/rewards/ledger')->assertOk()->assertJsonCount(1, 'data');
});

it('separates several relationships', function () {
    $secondMentee = User::factory()->create(['name' => 'Second child']);
    $second = Mentorship::factory()->accepted()->between($this->mentor, $secondMentee)->create();

    Reward::factory()->fulfilled()->monetary(500, 'BDT')->create(['mentorship_id' => $this->mentorship->id]);
    Reward::factory()->fulfilled()->monetary(250, 'BDT')->create(['mentorship_id' => $second->id]);

    Sanctum::actingAs($this->mentor);

    $this->getJson('/api/v1/rewards/ledger')->assertOk()->assertJsonCount(2, 'data');
});

it('returns an empty ledger rather than failing when nothing was delivered', function () {
    Sanctum::actingAs($this->mentor);

    $this->getJson('/api/v1/rewards/ledger')->assertOk()->assertJsonPath('data', []);
});
