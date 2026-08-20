<?php

namespace App\Http\Resources;

use App\Models\Sprint;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mirrored by `types/sprint.ts` in the SPA (03 §2).
 *
 * `started_at` and `planned_duration_seconds` are the contract that makes the
 * timer survive a closed browser: the SPA recomputes remaining time from them
 * rather than counting ticks (FR-SPR-03, 03 §4). The derived fields below are
 * sent as a convenience and a cross-check — the client is expected to keep
 * computing its own, since it re-renders far more often than it refetches.
 *
 * @mixin Sprint
 */
class SprintResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $now = CarbonImmutable::now();

        return [
            'id' => $this->id,
            'goal_id' => $this->goal_id,
            'roadmap_item_id' => $this->roadmap_item_id,
            'mode' => $this->mode,
            'planned_duration_seconds' => $this->planned_duration_seconds,
            'break_seconds' => $this->break_seconds,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'paused_at' => $this->paused_at?->toIso8601String(),
            'paused_seconds_total' => $this->paused_seconds_total,
            'actual_duration_seconds' => $this->actual_duration_seconds,
            'status' => $this->status,
            'notes' => $this->notes,
            'notified_expired_at' => $this->notified_expired_at?->toIso8601String(),

            /** Derived, never stored — there is no `overtime` status (FR-SPR-09). */
            'deadline_at' => $this->deadlineAt()?->toIso8601String(),
            'is_overtime' => $this->isOvertime(),
            'overtime_seconds' => $this->overtimeSeconds(),
            'focus_seconds_so_far' => $this->focusSecondsAt($now),

            'goal' => new GoalResource($this->whenLoaded('goal')),
            'roadmap_item' => new RoadmapItemResource($this->whenLoaded('roadmapItem')),
        ];
    }
}
