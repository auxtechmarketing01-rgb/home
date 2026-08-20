<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated user's own profile. Never use this to expose another
 * member — those are rendered as a `{ id, name }` summary instead (02 §5).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_path' => $this->avatar_path,
            'timezone' => $this->timezone,
            'xp' => $this->xp,
            'level' => $this->level,
            'gamification_enabled' => $this->hasGamificationEnabled(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
        ];
    }
}
