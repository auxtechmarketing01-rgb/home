<?php

use App\Models\ActivityLog;
use App\Models\Badge;
use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use App\Services\GamificationService;

/**
 * FR-GAM-02 (XP and levels) and FR-GAM-03 (badges).
 */
beforeEach(function () {
    $this->gamification = app(GamificationService::class);

    foreach (['streak_7', 'streak_30', 'streak_100', 'first_goal_completed'] as $key) {
        Badge::factory()->create(['key' => $key]);
    }

    $this->user = User::factory()->create();
    $this->goal = Goal::factory()->for($this->user)->create();
    $this->roadmap = Roadmap::factory()->for($this->goal)->create();
});

it('awards xp for focus minutes and completed items', function () {
    config([
        'pathforge.gamification.xp_per_focus_minute' => 1,
        'pathforge.gamification.xp_per_roadmap_item' => 25,
        'pathforge.gamification.xp_per_level' => 500,
    ]);

    /** 60 minutes of focus. */
    Sprint::factory()->for($this->user)->completed(3600)->create(['goal_id' => $this->goal->id]);

    $item = RoadmapItem::factory()->for($this->roadmap)->done()->create();
    ActivityLog::factory()->create([
        'user_id' => $this->user->id,
        'subject_type' => RoadmapItem::class,
        'subject_id' => $item->id,
        'action' => 'roadmap_item.completed',
    ]);

    $result = $this->gamification->recalculateFor($this->user);

    expect($result)->toBe(['xp' => 85, 'level' => 1]);

    expect($this->user->fresh()->xp)->toBe(85);
});

it('levels up on the configured curve', function () {
    config([
        'pathforge.gamification.xp_per_focus_minute' => 1,
        'pathforge.gamification.xp_per_level' => 100,
    ]);

    Sprint::factory()->for($this->user)->completed(3600 * 4)->create(['goal_id' => $this->goal->id]);

    $result = $this->gamification->recalculateFor($this->user);

    expect($result['xp'])->toBe(240)
        ->and($result['level'])->toBe(3);
});

/**
 * Rebuilt from source rather than incremented, for the same reason as the
 * goal stats rollup: a missed or double-run pass would otherwise leave a
 * permanently wrong number with nothing to notice it.
 */
it('rebuilds xp rather than accumulating it', function () {
    config(['pathforge.gamification.xp_per_focus_minute' => 1]);

    Sprint::factory()->for($this->user)->completed(3600)->create(['goal_id' => $this->goal->id]);

    $this->gamification->recalculateFor($this->user);
    $this->gamification->recalculateFor($this->user);
    $this->gamification->recalculateFor($this->user);

    expect($this->user->fresh()->xp)->toBe(60);
});

it('computes nothing for a member who opted out', function () {
    $user = User::factory()->withoutGamification()->create();
    Goal::factory()->for($user)->create();
    Sprint::factory()->for($user)->completed(3600)->create();

    expect($this->gamification->recalculateFor($user))->toBeNull()
        ->and($user->fresh()->xp)->toBe(0);
});

it('ignores cancelled sprints', function () {
    config(['pathforge.gamification.xp_per_focus_minute' => 1]);

    Sprint::factory()->for($this->user)->completed(1800)->create(['goal_id' => $this->goal->id]);
    Sprint::factory()->for($this->user)->cancelled()->create(['goal_id' => $this->goal->id]);

    expect($this->gamification->recalculateFor($this->user)['xp'])->toBe(30);
});

it('awards the first goal completed badge', function () {
    $this->goal->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

    $awarded = $this->gamification->awardBadges($this->user, 0);

    expect($awarded)->toBe(['first_goal_completed']);
});

it('awards every streak milestone reached', function () {
    $awarded = $this->gamification->awardBadges($this->user, 100);

    expect($awarded)->toContain('streak_7')
        ->toContain('streak_30')
        ->toContain('streak_100');
});

it('awards nothing below the first milestone', function () {
    expect($this->gamification->awardBadges($this->user, 6))->toBe([]);
});

it('is idempotent when awarding', function () {
    $this->gamification->awardBadges($this->user, 30);
    $second = $this->gamification->awardBadges($this->user, 30);

    expect($second)->toBe([])
        ->and($this->user->fresh()->badges)->toHaveCount(2);
});

/**
 * XP already earned is not taken back when a goal is archived — the member did
 * the work.
 */
it('keeps xp for items completed on a goal that was later archived', function () {
    config(['pathforge.gamification.xp_per_roadmap_item' => 25]);

    $item = RoadmapItem::factory()->for($this->roadmap)->done()->create();
    ActivityLog::factory()->create([
        'user_id' => $this->user->id,
        'subject_type' => RoadmapItem::class,
        'subject_id' => $item->id,
        'action' => 'roadmap_item.completed',
    ]);

    $this->goal->delete();

    expect($this->gamification->recalculateFor($this->user)['xp'])->toBe(25);
});
