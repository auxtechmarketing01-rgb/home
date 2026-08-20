<?php

namespace App\Http\Resources;

use App\Models\Mentorship;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mirrored by `types/mentorship.ts` (03 §2), which types both parties as
 * `{ id, name }` — a summary, never a full user record, since the counterpart
 * is another member.
 *
 * `requested_by_user_id` is exposed because the SPA needs it to decide who is
 * waiting on whom, and because only the *other* party may respond
 * (FR-MENT-02).
 *
 * @mixin Mentorship
 */
class MentorshipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actingUserId = $request->user()?->id;

        return [
            'id' => $this->id,
            'mentor' => $this->whenLoaded('mentor', fn (): array => [
                'id' => $this->mentor->id,
                'name' => $this->mentor->name,
            ]),
            'mentee' => $this->whenLoaded('mentee', fn (): array => [
                'id' => $this->mentee->id,
                'name' => $this->mentee->name,
            ]),
            'status' => $this->status,
            'requested_by_user_id' => $this->requested_by_user_id,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            /** Convenience flags so the SPA does not re-derive the rules. */
            'viewer_role' => match ($actingUserId) {
                $this->mentor_id => 'mentor',
                $this->mentee_id => 'mentee',
                default => null,
            },
            'viewer_can_respond' => $actingUserId !== null
                && $this->status === 'pending'
                && $this->requested_by_user_id !== $actingUserId,
        ];
    }
}
