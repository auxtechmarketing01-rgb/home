<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mirrored by `types/group.ts`'s `LeaderboardEntry` (03 §2).
 *
 * Wraps the plain arrays LeaderboardService produces rather than a model —
 * the entries are computed aggregates, and there is deliberately nothing in
 * them that could be traced back to an individual private goal
 * (01 §5 Privacy).
 */
class LeaderboardEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $entry */
        $entry = $this->resource;

        return [
            'user' => $entry['user'],
            'focus_minutes' => $entry['focus_minutes'],
            'current_streak' => $entry['current_streak'],
            'goals_completed' => $entry['goals_completed'],
        ];
    }
}
