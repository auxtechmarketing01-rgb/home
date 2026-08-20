<?php

use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/**
 * FR-SPR-06.
 */
beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->goal = Goal::factory()->for($this->user)->create(['title' => 'Learn C Programming']);
    $roadmap = Roadmap::factory()->for($this->goal)->create();
    $this->item = RoadmapItem::factory()->for($roadmap)->create(['title' => 'Day 1 – Variables']);

    Sanctum::actingAs($this->user);
});

it('exports a csv with the expected header row', function () {
    $response = $this->get('/api/v1/sprints/export')->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('sprints.csv');

    $lines = preg_split('/\r\n|\n/', trim($response->streamedContent()));

    expect($lines[0])->toBe(
        'id,started_at,ended_at,mode,status,planned_duration_seconds,'
        .'actual_duration_seconds,paused_seconds_total,goal,roadmap_item,notes'
    );
});

it('includes a completed sprint with its goal and item titles', function () {
    $sprint = Sprint::factory()->for($this->user)->completed(1500)->create([
        'goal_id' => $this->goal->id,
        'roadmap_item_id' => $this->item->id,
        'notes' => 'Pointers finally clicked',
    ]);

    $content = $this->get('/api/v1/sprints/export')->assertOk()->streamedContent();

    expect($content)->toContain((string) $sprint->id)
        ->and($content)->toContain('Learn C Programming')
        ->and($content)->toContain('Day 1 – Variables')
        ->and($content)->toContain('Pointers finally clicked')
        ->and($content)->toContain('1500');
});

it('applies the date range filter in the member own timezone', function () {
    $this->user->update(['timezone' => 'Asia/Dhaka']);

    /**
     * 22:30 UTC on the 9th is already 04:30 on the 10th in Dhaka, so a member
     * filtering "from the 10th" must see it. Comparing raw UTC dates would
     * silently drop the session from their own report.
     */
    $lateEvening = Sprint::factory()->for($this->user)->completed()->create([
        'started_at' => '2026-03-09 22:30:00',
    ]);

    $earlier = Sprint::factory()->for($this->user)->completed()->create([
        'started_at' => '2026-03-01 10:00:00',
    ]);

    $content = $this->get('/api/v1/sprints/export?from=2026-03-10')->assertOk()->streamedContent();

    expect($content)->toContain((string) $lateEvening->id)
        ->and($content)->not->toContain('2026-03-01');
});

it('filters the history by goal and status', function () {
    $otherGoal = Goal::factory()->for($this->user)->create();

    $wanted = Sprint::factory()->for($this->user)->completed()->create(['goal_id' => $this->goal->id]);
    Sprint::factory()->for($this->user)->completed()->create(['goal_id' => $otherGoal->id]);
    Sprint::factory()->for($this->user)->cancelled()->create(['goal_id' => $this->goal->id]);

    $this->getJson("/api/v1/sprints?goal_id={$this->goal->id}&status=completed")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $wanted->id);
});

it('rejects a reversed date range', function () {
    $this->getJson('/api/v1/sprints?from=2026-03-10&to=2026-03-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
});
