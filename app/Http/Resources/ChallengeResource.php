<?php

namespace App\Http\Resources;

use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FR-GRP-04.
 *
 * A participant's goal is reported by id and title only. The comparison view
 * shows how everyone is *doing*, and that does not require handing over
 * another member's full goal record.
 *
 * @mixin Challenge
 */
class ChallengeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'created_by' => $this->created_by,
            'participants_count' => $this->whenCounted('participants'),
            'has_joined' => $request->user() !== null
                ? $this->participants->contains('user_id', $request->user()->id)
                : false,
            'participants' => $this->whenLoaded('participants', fn (): array => $this->participants
                ->map(fn (ChallengeParticipant $participant): array => [
                    'user' => [
                        'id' => $participant->user_id,
                        'name' => $participant->user?->name,
                    ],
                    'goal' => $participant->goal === null ? null : [
                        'id' => $participant->goal->id,
                        'title' => $participant->goal->title,
                        'status' => $participant->goal->status,
                        'completion_percentage' => (float) ($participant->goal->stats->completion_percentage ?? 0),
                        'total_focus_seconds' => (int) ($participant->goal->stats->total_focus_seconds ?? 0),
                    ],
                    'joined_at' => $participant->joined_at?->toIso8601String(),
                ])->all()),
        ];
    }
}
