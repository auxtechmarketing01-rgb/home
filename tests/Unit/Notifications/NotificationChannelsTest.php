<?php

use App\Models\Challenge;
use App\Models\Goal;
use App\Models\Group;
use App\Models\Mentorship;
use App\Models\Reward;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use App\Notifications\ChallengeUpdateNotification;
use App\Notifications\GroupInviteNotification;
use App\Notifications\MemberNotification;
use App\Notifications\MentorshipAcceptedNotification;
use App\Notifications\MentorshipRequestedNotification;
use App\Notifications\RewardClaimedNotification;
use App\Notifications\RewardEarnedNotification;
use App\Notifications\RewardFulfilledNotification;
use App\Notifications\RewardOfferedNotification;
use App\Notifications\RewardRequestRespondedNotification;
use App\Notifications\RoadmapItemAssignedNotification;
use App\Notifications\SprintCompleteNotification;
use App\Notifications\StreakAtRiskNotification;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * One place that pins **which notifications are allowed to interrupt a member**
 * at the OS level, and which are not.
 *
 * Web Push is the only channel that reaches a closed browser, and it costs the
 * member an interruption — so it is opted into per notification. The research
 * behind this product flagged gamification fatigue and over-notification as
 * real causes of app abandonment, so this list is a product decision, not an
 * implementation detail. Adding a notification to the interrupting set should
 * require changing this test on purpose.
 *
 * `database` + `broadcast` are on every one of them without exception: the
 * durable record must never be optional, and the live frame is free.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
});

/**
 * @return array<string, MemberNotification>
 */
function everyMemberNotification(): array
{
    $user = User::factory()->create();
    $mentee = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $user->id]);
    $mentorship = Mentorship::factory()->accepted()->between($user, $mentee)->create();
    $reward = Reward::factory()->create(['mentorship_id' => $mentorship->id]);
    $challenge = Challenge::factory()->create(['group_id' => $group->id, 'created_by' => $user->id]);

    $goal = Goal::factory()->for($mentee)->create();
    $item = RoadmapItem::factory()->for(Roadmap::factory()->for($goal)->create())->create();

    return [
        SprintCompleteNotification::class => new SprintCompleteNotification(
            Sprint::factory()->for($user)->running()->create()
        ),
        GroupInviteNotification::class => new GroupInviteNotification($group, $user),
        StreakAtRiskNotification::class => new StreakAtRiskNotification(5),
        ChallengeUpdateNotification::class => new ChallengeUpdateNotification($challenge, $user),
        MentorshipRequestedNotification::class => new MentorshipRequestedNotification($mentorship, $mentee),
        MentorshipAcceptedNotification::class => new MentorshipAcceptedNotification($mentorship, $user),
        RoadmapItemAssignedNotification::class => new RoadmapItemAssignedNotification($item, $user),
        RewardOfferedNotification::class => new RewardOfferedNotification($reward),
        RewardEarnedNotification::class => new RewardEarnedNotification($reward),
        RewardClaimedNotification::class => new RewardClaimedNotification($reward),
        RewardFulfilledNotification::class => new RewardFulfilledNotification($reward),
        RewardRequestRespondedNotification::class => new RewardRequestRespondedNotification($reward),
    ];
}

it('always writes to the notification centre and always broadcasts', function () {
    $offenders = [];

    foreach (everyMemberNotification() as $class => $notification) {
        $channels = $notification->via($this->user);

        if (! in_array('database', $channels, true) || ! in_array('broadcast', $channels, true)) {
            $offenders[] = class_basename($class);
        }
    }

    expect($offenders)->toBe([]);
});

/**
 * The exact interrupting set, and the reason each one earns it:
 *
 * - GroupInviteNotification       FR-GRP-01, usually delivered while the two
 *                                 people are physically together
 * - MentorshipRequestedNotification  FR-MENT-02, nothing moves until the
 *                                 recipient answers
 * - RewardClaimedNotification     FR-RWD-04, the forgotten-delivery failure the
 *                                 whole reward system guards against
 * - RewardFulfilledNotification   FR-RWD-05, closes the loop the mentee has
 *                                 been waiting on
 * - SprintCompleteNotification    FR-SPR-10, the entire reason Web Push exists
 *                                 in this app
 *
 * If this assertion fails, the interrupting set changed — check that it was
 * deliberate rather than updating the expectation.
 */
it('reaches a closed browser for exactly the notifications that warrant it', function () {
    $interrupting = [];

    foreach (everyMemberNotification() as $class => $notification) {
        if (in_array(WebPushChannel::class, $notification->via($this->user), true)) {
            $interrupting[] = class_basename($class);
        }
    }

    sort($interrupting);

    expect($interrupting)->toBe([
        'GroupInviteNotification',
        'MentorshipRequestedNotification',
        'RewardClaimedNotification',
        'RewardFulfilledNotification',
        'SprintCompleteNotification',
    ]);
});

/**
 * A streak reminder must never wake a phone — that is exactly the
 * over-notification the research warned about.
 */
it('never interrupts for a streak reminder or a challenge update', function () {
    expect((new StreakAtRiskNotification(5))->via($this->user))
        ->not->toContain(WebPushChannel::class);
});

/**
 * Every notification opting into Web Push must actually implement the message,
 * or delivery fails at runtime with nothing having warned anybody.
 */
it('implements toWebPush wherever it opts in', function () {
    $missing = [];

    foreach (everyMemberNotification() as $class => $notification) {
        if (! in_array(WebPushChannel::class, $notification->via($this->user), true)) {
            continue;
        }

        if (! method_exists($notification, 'toWebPush')) {
            $missing[] = class_basename($class);
        }
    }

    expect($missing)->toBe([]);
});

/**
 * The wire format must never carry a PHP namespace, and every payload must
 * survive a JSON round trip — it is persisted in `notifications.data`.
 */
it('produces a short type name and a serializable payload', function () {
    foreach (everyMemberNotification() as $class => $notification) {
        expect($notification->broadcastType())->toBe(class_basename($class))
            ->and($notification->broadcastType())->not->toContain('\\');

        $payload = $notification->toArray($this->user);

        expect(json_decode(json_encode($payload), true))->toBe($payload);
    }
});

/**
 * The nested `payload` key is what keeps a notification's own fields from
 * shadowing the `id` and `type` Laravel merges into the frame.
 */
it('nests every broadcast payload', function () {
    $drifted = [];

    foreach (everyMemberNotification() as $class => $notification) {
        if (array_keys($notification->toBroadcast($this->user)->data) !== ['payload', 'read_at', 'created_at']) {
            $drifted[] = class_basename($class);
        }
    }

    expect($drifted)->toBe([]);
});
