<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Base class for every notification sent to a member.
 *
 * There are three delivery channels and they are not interchangeable — each
 * covers a state the others cannot reach:
 *
 * - `database` is the notification centre. It is the durable record, so it is
 *   never optional; a notification that is only pushed is a notification the
 *   user can lose (FR-NOT-01).
 * - `broadcast` (Pusher Channels) makes the same payload arrive immediately in
 *   an open tab, on the member's private `App.Models.User.{id}` channel, so a
 *   status change like `offered` -> `earned` shows up without a refetch
 *   (FR-NOT-03, and the reason 06 §3 gate 5 can assert "without refreshing").
 *   It reaches only a live WebSocket connection: close the tab and it is gone.
 * - `webpush` (VAPID) is the only channel that reaches a member whose tab and
 *   window are shut, which is precisely what FR-SPR-10 asks for. It costs the
 *   user an OS-level interruption, so subclasses opt in individually rather
 *   than getting it by default.
 *
 * `toArray()` is the single source of the payload: the database record, the
 * broadcast frame and the SPA's `Notification` type all read the same shape,
 * so a live update and a later reload of the notification centre can never
 * disagree.
 *
 * The payload is nested under a `payload` key rather than sitting at the top
 * level of the frame. That is deliberate: Laravel merges `id` and `type` into
 * the broadcast frame, and a flat payload would let any notification's own
 * field silently shadow one of them.
 */
abstract class MemberNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($this->reachesClosedBrowser()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * The payload. Keep it flat, serializable and stable — it is persisted in
     * `notifications.data` and read back by clients built against an older
     * version of this class.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(object $notifiable): array;

    /**
     * Laravel merges `id` and `type` into the frame, so what arrives over
     * Pusher is field-for-field what `GET /notifications` returns for the
     * same record — the SPA can render a live frame and a refetched row with
     * one code path.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'payload' => $this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * The short, stable name the SPA switches on. Without this the wire
     * format would carry a PHP FQCN, which couples the client to backend
     * namespaces.
     */
    public function broadcastType(): string
    {
        return class_basename($this);
    }

    /**
     * Whether this notification is worth an OS-level interruption when the
     * browser is closed. Default: no.
     */
    protected function reachesClosedBrowser(): bool
    {
        return false;
    }
}
