<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\FileStorageService;
use Illuminate\Http\UploadedFile;

class UpdateProfileAction
{
    public function __construct(private FileStorageService $files) {}

    /**
     * FR-AUTH-04. `timezone` is part of this because it decides where the
     * user's day boundary falls for streak math (FR-GAM-01).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(User $user, array $attributes, ?UploadedFile $avatar = null): User
    {
        if ($avatar instanceof UploadedFile) {
            $previousDisk = (string) config('pathforge.uploads.disk');
            $previousPath = $user->avatar_path;

            $stored = $this->files->store($avatar, 'avatars');
            $attributes['avatar_path'] = $stored['path'];

            $this->files->delete($previousDisk, $previousPath);
        }

        if (array_key_exists('settings', $attributes)) {
            $attributes['settings'] = array_merge($user->settings ?? [], $attributes['settings']);
        }

        $user->fill($attributes)->save();

        return $user;
    }
}
