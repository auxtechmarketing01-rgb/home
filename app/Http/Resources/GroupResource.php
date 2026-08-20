<?php

namespace App\Http\Resources;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mirrored by `types/group.ts` in the SPA (03 §2).
 *
 * `invite_code` is exposed **only to the owner**. It is the single credential
 * that grants entry to the group, so handing it to every member would let any
 * one of them invite strangers into a circle that may include minors
 * (FR-GRP-01, 01 §8).
 *
 * @mixin Group
 */
class GroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'owner_id' => $this->owner_id,
            'is_owner' => $request->user()?->id === $this->owner_id,
            'invite_code' => $this->when(
                $request->user()?->id === $this->owner_id,
                fn (): ?string => $this->invite_code,
            ),
            'members_count' => $this->whenCounted('members'),
            'members' => $this->whenLoaded('members', fn (): array => $this->members
                ->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'role' => $member->pivot->role,
                ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
