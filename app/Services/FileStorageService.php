<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Thin wrapper over Laravel's filesystem abstraction so local vs. S3 stays a
 * config change rather than a code change (02 §8, 01 NFR Portability).
 */
class FileStorageService
{
    /**
     * Stores an upload and returns the columns the caller persists.
     *
     * @return array{disk: string, path: string, mime_type: string, size_bytes: int}
     */
    public function store(UploadedFile $file, string $directory, ?string $disk = null): array
    {
        $disk ??= (string) config('pathforge.uploads.disk');

        $path = $file->store($directory, $disk);

        return [
            'disk' => $disk,
            'path' => $path,
            /**
             * `getMimeType()` on a stored upload reads the file's bytes, not
             * the client-supplied Content-Type header.
             */
            'mime_type' => (string) (Storage::disk($disk)->mimeType($path) ?: $file->getMimeType()),
            'size_bytes' => (int) Storage::disk($disk)->size($path),
        ];
    }

    public function delete(string $disk, ?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk($disk)->delete($path);
    }
}
