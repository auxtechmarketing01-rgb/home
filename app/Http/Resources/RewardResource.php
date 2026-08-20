<?php

namespace App\Http\Resources;

use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mirrored by `types/reward.ts` (03 §2).
 *
 * `monetary_amount` is a record of what was promised, never a balance —
 * nothing here can be spent inside the app (FR-RWD-05, 01 NFR Financial
 * integrity). The SPA is expected to label it as such.
 *
 * @mixin Reward
 */
class RewardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actingUserId = $request->user()?->id;
        $mentorship = $this->mentorship;

        $viewerRole = match (true) {
            $mentorship === null || $actingUserId === null => null,
            $mentorship->mentor_id === $actingUserId => 'mentor',
            $mentorship->mentee_id === $actingUserId => 'mentee',
            default => null,
        };

        return [
            'id' => $this->id,
            'mentorship_id' => $this->mentorship_id,
            'goal_id' => $this->goal_id,
            'roadmap_item_id' => $this->roadmap_item_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'monetary_amount' => $this->monetary_amount,
            'currency_label' => $this->currency_label,
            'status' => $this->status,
            'requested_by' => $this->requested_by,
            'claimed_at' => $this->claimed_at?->toIso8601String(),
            'fulfilled_at' => $this->fulfilled_at?->toIso8601String(),
            'fulfilled_note' => $this->fulfilled_note,

            'viewer_role' => $viewerRole,
            /**
             * The exact set of transitions this viewer may trigger right now.
             * Computed from the same side-and-state pair the Policy and Action
             * enforce, so the UI cannot offer a button the API will refuse —
             * and cannot hide one it would have allowed (03 §2.2 RewardCard).
             */
            'available_actions' => $this->availableActions($viewerRole),

            'goal' => new GoalResource($this->whenLoaded('goal')),
            'roadmap_item' => new RoadmapItemResource($this->whenLoaded('roadmapItem')),
        ];
    }

    /**
     * @return list<string>
     */
    protected function availableActions(?string $viewerRole): array
    {
        if ($viewerRole === null || ! ($this->mentorship?->isAccepted() ?? false)) {
            return [];
        }

        return match ([$viewerRole, $this->status]) {
            ['mentor', 'requested'] => ['respond'],
            ['mentor', 'offered'] => ['revoke'],
            ['mentor', 'claimed'] => ['fulfill'],
            ['mentee', 'earned'] => ['claim'],
            default => [],
        };
    }
}
