<?php

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\Fixtures\TestMemberNotification;
use Tests\Fixtures\TestPushMemberNotification;

/**
 * FR-NOT-01 / FR-NOT-03: the durable record and the live frame are not
 * alternatives. Every member notification takes both.
 */
it('delivers over the notification centre and Pusher by default', function () {
    $user = User::factory()->create();

    expect((new TestMemberNotification)->via($user))->toBe(['database', 'broadcast']);
});

/**
 * Web Push is the only channel that reaches a closed browser, and it costs
 * the member an OS-level interruption — so it is opted into, never default.
 */
it('adds the web push channel only when a notification opts in', function () {
    $user = User::factory()->create();

    expect((new TestMemberNotification)->via($user))
        ->not->toContain(WebPushChannel::class)
        ->and((new TestPushMemberNotification)->via($user))
        ->toBe(['database', 'broadcast', WebPushChannel::class]);
});

/**
 * The live frame and a later reload of the notification centre must never
 * disagree, which is why both read the same toArray().
 */
it('broadcasts exactly the payload it persists', function () {
    $user = User::factory()->create();
    $notification = new TestMemberNotification('Reward earned.');

    expect($notification->toBroadcast($user)->data['payload'])
        ->toBe($notification->toArray($user));
});

/**
 * Laravel merges `id` and `type` into the frame; nesting the payload keeps a
 * notification's own fields from shadowing either of them, and makes the
 * frame field-for-field identical to what NotificationResource returns.
 */
it('nests the payload so it cannot shadow the id or type of the frame', function () {
    $user = User::factory()->create();

    expect(array_keys((new TestMemberNotification)->toBroadcast($user)->data))
        ->toBe(['payload', 'read_at', 'created_at']);
});

it('broadcasts under the short class name so clients never see a php namespace', function () {
    expect((new TestMemberNotification)->broadcastType())->toBe('TestMemberNotification');
});

/**
 * The channel name is the privacy boundary that routes/channels.php
 * authorizes. If this drifts, either notifications stop arriving or they
 * arrive on a channel nobody guards.
 */
it('targets the member private channel that channels.php authorizes', function () {
    $user = User::factory()->create();
    $notification = new TestMemberNotification;
    $notification->id = 'a-uuid';

    $channels = (new BroadcastNotificationCreated($user, $notification, []))->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and((string) $channels[0])->toBe('private-App.Models.User.'.$user->id);
});
