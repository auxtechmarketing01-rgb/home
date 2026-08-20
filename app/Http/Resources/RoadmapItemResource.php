<?php

namespace App\Http\Resources;

use App\Models\RoadmapItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mirrored by `types/roadmap.ts` in the SPA (03 §2). The mentor-assignment
 * fields are added in Phase 4.
 *
 * @mixin RoadmapItem
 */
class RoadmapItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'roadmap_id' => $this->roadmap_id,
            'parent_id' => $this->parent_id,
            'title' => $this->title,
            'description' => $this->description,
            'day_number' => $this->day_number,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'estimated_minutes' => $this->estimated_minutes,
            'time_spent_seconds' => $this->time_spent_seconds,
            'status' => $this->status,
            'position' => $this->position,
            'reflection_note' => $this->reflection_note,

            /**
             * FR-MENT-05. `assigned_minutes` is the mentor's expectation and
             * is deliberately distinct from `estimated_minutes` above, which
             * is the member's own estimate — the SPA must never conflate them
             * (03 §2).
             */
            'assigned_by_mentor' => $this->whenLoaded('assignedByMentor', fn (): ?array => $this->assignedByMentor === null
                ? null
                : ['id' => $this->assignedByMentor->id, 'name' => $this->assignedByMentor->name]),
            'assigned_minutes' => $this->assigned_minutes,
            'assigned_due_at' => $this->assigned_due_at?->toIso8601String(),

            'children' => RoadmapItemResource::collection($this->whenLoaded('children')),
        ];
    }
}
