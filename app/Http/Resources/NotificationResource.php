<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Mirrored by `types/notification.ts` in the SPA (03 §2). The same shape
 * arrives over Pusher, so a live frame and a refetch render identically.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /**
             * `type` is stored as the notification's FQCN; the short name is
             * what the SPA switches on, matching MemberNotification::broadcastType().
             */
            'type' => class_basename($this->type),
            /**
             * Named `payload`, not `data`: `data` is also the resource
             * wrapper key, and a top-level `data` field makes Laravel treat
             * the body as already wrapped and drop the envelope.
             */
            'payload' => $this->data,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
