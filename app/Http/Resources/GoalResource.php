<?php

namespace App\Http\Resources;

use App\Models\Goal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mirrored by `types/goal.ts` in the SPA (03 §2) — keep the two in step.
 *
 * @mixin Goal
 */
class GoalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'target_start_date' => $this->target_start_date?->toDateString(),
            'target_end_date' => $this->target_end_date?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'group_id' => $this->group_id,
            'group' => new GroupResource($this->whenLoaded('group')),
            /**
             * The analytics cache, read straight from `goal_stats` — never
             * recomputed per request (02 §7). Absent until the first
             * RecalculateGoalStatsJob has run for this goal.
             */
            'stats' => new GoalStatsResource($this->whenLoaded('stats')),
            'roadmap' => new RoadmapResource($this->whenLoaded('roadmap')),
            'roadmap_item_count' => $this->whenCounted('roadmapItems'),
            /**
             * Owner summary, present only when the relation was loaded — a
             * group comparison view needs to know whose goal this is without
             * exposing anything else about that member (02 §5).
             */
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
        ];
    }
}
