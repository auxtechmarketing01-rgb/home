<?php

namespace App\Actions\Resources;

use App\Models\ActivityLog;
use App\Models\ResourceFile;
use App\Models\User;
use App\Services\FileStorageService;
use Illuminate\Support\Facades\DB;

class DeleteResourceFileAction
{
    public function __construct(private FileStorageService $files) {}

    public function __invoke(User $actor, ResourceFile $resource): void
    {
        $disk = $resource->disk;
        $path = $resource->path;
        $wasStoredFile = $resource->isStoredFile();
        $resourceId = $resource->id;
        $parentType = $resource->resourceable_type;
        $parentId = $resource->resourceable_id;

        DB::transaction(function () use ($resource, $actor, $resourceId, $parentType, $parentId): void {
            $resource->delete();

            ActivityLog::create([
                'user_id' => $actor->id,
                'subject_type' => $parentType,
                'subject_id' => $parentId,
                'action' => 'resource.detached',
                'meta' => ['resource_file_id' => $resourceId],
            ]);
        });

        /**
         * The blob is removed only after the row is committed. Doing it
         * inside the transaction would leave the file gone but the row intact
         * if the transaction later rolled back — an attachment pointing at
         * nothing, which is worse than an orphaned blob.
         */
        if ($wasStoredFile && $disk !== null) {
            $this->files->delete($disk, $path);
        }
    }
}
