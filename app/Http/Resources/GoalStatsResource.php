<?php

namespace App\Http\Resources;

use App\Models\GoalStats;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mirrored by `GoalStats` in `types/goal.ts` (03 §2).
 *
 * `projected_completion_date` is nullable on purpose and the SPA must render
 * that as "not enough data yet" — ProjectionService returns null rather than
 * fabricating a date from thin evidence (FR-ANL-02).
 *
 * @mixin GoalStats
 */
class GoalStatsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_focus_seconds' => $this->total_focus_seconds,
            'sessions_count' => $this->sessions_count,
            'completion_percentage' => $this->completion_percentage,
            'current_streak' => $this->current_streak,
            'longest_streak' => $this->longest_streak,
            'projected_completion_date' => $this->projected_completion_date?->toDateString(),
            'last_recalculated_at' => $this->last_recalculated_at?->toIso8601String(),
        ];
    }
}
