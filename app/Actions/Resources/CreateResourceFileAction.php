<?php

namespace App\Actions\Resources;

use App\Models\ActivityLog;
use App\Models\Goal;
use App\Models\ResourceFile;
use App\Models\RoadmapItem;
use App\Models\User;
use App\Services\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateResourceFileAction
{
    public function __construct(private FileStorageService $files) {}

    /**
     * FR-RES-01/02. Attaches a file, link or note to a Goal or RoadmapItem.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(
        User $actor,
        Goal|RoadmapItem $parent,
        array $attributes,
        ?UploadedFile $upload = null,
    ): ResourceFile {
        return DB::transaction(function () use ($actor, $parent, $attributes, $upload): ResourceFile {
            $resource = new ResourceFile;
            $resource->fill(Arr::only($attributes, ['type', 'title', 'url', 'body']));
            $resource->uploaded_by = $actor->id;

            /**
             * One table serves three kinds of attachment, so the columns that
             * do not apply to this kind are explicitly cleared rather than
             * left to whatever the payload happened to contain. Otherwise a
             * `note` could carry a stale `url` that the UI would render.
             */
            $this->normalizeByType($resource, $upload);

            $parent->resourceFiles()->save($resource);

            ActivityLog::create([
                'user_id' => $actor->id,
                'subject_type' => $parent::class,
                'subject_id' => $parent->id,
                'action' => 'resource.attached',
                'meta' => ['resource_file_id' => $resource->id, 'type' => $resource->type],
            ]);

            return $resource;
        });
    }

    protected function normalizeByType(ResourceFile $resource, ?UploadedFile $upload): void
    {
        match ($resource->type) {
            'file' => $this->applyUpload($resource, $upload),
            'link' => $resource->forceFill([
                'body' => null,
                'disk' => null,
                'path' => null,
                'mime_type' => null,
                'size_bytes' => null,
            ]),
            'note' => $resource->forceFill([
                'url' => null,
                'disk' => null,
                'path' => null,
                'mime_type' => null,
                'size_bytes' => null,
            ]),
            default => null,
        };
    }

    protected function applyUpload(ResourceFile $resource, ?UploadedFile $upload): void
    {
        if (! $upload instanceof UploadedFile) {
            return;
        }

        $stored = $this->files->store($upload, (string) config('pathforge.uploads.directory'));

        $resource->forceFill($stored + ['url' => null, 'body' => null]);
    }
}
