<?php

namespace App\Http\Resources;

use App\Models\ResourceFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Named ResourceFileResource for the `ResourceFile` model — the domain
 * "Resource" kept clear of Laravel's `Http\Resources` (02 §2).
 *
 * `disk` and `path` are deliberately not exposed. They describe server
 * internals, and leaking a storage path invites clients to construct URLs
 * that bypass the policy check.
 *
 * @mixin ResourceFile
 */
class ResourceFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'url' => $this->url,
            'body' => $this->body,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'created_at' => $this->created_at?->toIso8601String(),
            'uploaded_by' => $this->whenLoaded('uploader', fn (): array => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ]),
        ];
    }
}
