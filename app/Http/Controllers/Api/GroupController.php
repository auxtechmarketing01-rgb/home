<?php

namespace App\Http\Controllers\Api;

use App\Actions\Groups\CreateGroupAction;
use App\Actions\Groups\InviteToGroupAction;
use App\Actions\Groups\JoinGroupAction;
use App\Actions\Groups\RegenerateInviteCodeAction;
use App\Actions\Groups\RemoveGroupMemberAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\InviteToGroupRequest;
use App\Http\Requests\JoinGroupRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class GroupController extends Controller
{
    /**
     * Groups the acting member belongs to — the query-side mirror of
     * GroupPolicy::view.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Group::class);

        $groups = Group::query()
            ->forMember($request->user())
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return GroupResource::collection($groups);
    }

    public function store(StoreGroupRequest $request, CreateGroupAction $createGroup): JsonResponse
    {
        $this->authorize('create', Group::class);

        $group = $createGroup($request->user(), $request->validated());

        return GroupResource::make($group->loadCount('members'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Group $group): GroupResource
    {
        $this->authorize('view', $group);

        return GroupResource::make($group->load('members')->loadCount('members'));
    }

    public function update(UpdateGroupRequest $request, Group $group): GroupResource
    {
        $this->authorize('update', $group);

        $group->update($request->validated());

        return GroupResource::make($group->loadCount('members'));
    }

    /**
     * FR-GRP-01. Owner-only, because the invite code is the credential that
     * grants entry.
     */
    public function invite(
        InviteToGroupRequest $request,
        Group $group,
        InviteToGroupAction $invite
    ): JsonResponse {
        $this->authorize('update', $group);

        $result = $invite($request->user(), $group, $request->validated()['email'] ?? null);

        return response()->json($result);
    }

    public function regenerateInviteCode(
        Request $request,
        Group $group,
        RegenerateInviteCodeAction $regenerate
    ): GroupResource {
        $this->authorize('update', $group);

        return GroupResource::make($regenerate($request->user(), $group));
    }

    /**
     * No Policy: anyone holding a valid code may join, which is the whole
     * point of a code. JoinGroupAction resolves it.
     */
    public function join(JoinGroupRequest $request, JoinGroupAction $join): JsonResponse
    {
        $group = $join($request->user(), $request->validated()['invite_code']);

        return GroupResource::make($group->loadCount('members'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * FR-GRP-05, owner removing somebody.
     */
    public function removeMember(
        Request $request,
        Group $group,
        User $member,
        RemoveGroupMemberAction $removeMember
    ): Response {
        $this->authorize('update', $group);

        $removeMember($request->user(), $group, $member);

        return response()->noContent();
    }

    /**
     * FR-GRP-05, a member leaving of their own accord. A distinct ability
     * from `update`: any member may leave, but the owner may not — a group
     * with no owner has nobody who can manage it.
     */
    public function leave(
        Request $request,
        Group $group,
        RemoveGroupMemberAction $removeMember
    ): Response {
        $this->authorize('leave', $group);

        $removeMember($request->user(), $group, $request->user());

        return response()->noContent();
    }

    public function destroy(Group $group): Response
    {
        $this->authorize('delete', $group);

        $group->delete();

        return response()->noContent();
    }
}
