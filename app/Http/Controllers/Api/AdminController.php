<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Http\Resources\UserResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * FR-ADM-01, deliberately minimal: view users and groups, and disable an
 * abusive account. Not a full admin panel — a handful of protected routes is
 * sufficient for a closed-group app, and the `admin` middleware guards all of
 * them.
 */
class AdminController extends Controller
{
    public function users(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()->orderBy('name')->paginate(50)
        );
    }

    public function groups(): AnonymousResourceCollection
    {
        return GroupResource::collection(
            Group::query()->withCount('members')->orderBy('name')->paginate(50)
        );
    }

    /**
     * Disabling takes effect on the member's very next request, not merely at
     * their next login — EnsureAccountIsActive tears the session down.
     *
     * @throws ValidationException
     */
    public function disableUser(User $user): JsonResponse
    {
        if ($user->isAdmin()) {
            throw ValidationException::withMessages([
                'user' => 'An administrator account cannot be disabled here.',
            ]);
        }

        $user->forceFill(['disabled_at' => now()])->save();

        return response()->json(['message' => 'Account disabled.']);
    }

    public function enableUser(User $user): JsonResponse
    {
        $user->forceFill(['disabled_at' => null])->save();

        return response()->json(['message' => 'Account enabled.']);
    }
}
