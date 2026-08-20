<?php

namespace App\Http\Controllers\Api;

use App\Actions\Challenges\CreateChallengeAction;
use App\Actions\Challenges\JoinChallengeAction;
use App\Actions\Challenges\LeaveChallengeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\JoinChallengeRequest;
use App\Http\Requests\StoreChallengeRequest;
use App\Http\Resources\ChallengeResource;
use App\Models\Challenge;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * FR-GRP-04. Everything is scoped through the parent group, so a challenge is
 * exactly as visible as the group and no more.
 */
class ChallengeController extends Controller
{
    public function index(Group $group): AnonymousResourceCollection
    {
        $this->authorize('view', $group);

        $challenges = $group->challenges()
            ->with('participants')
            ->withCount('participants')
            ->latest('id')
            ->get();

        return ChallengeResource::collection($challenges);
    }

    public function store(
        StoreChallengeRequest $request,
        Group $group,
        CreateChallengeAction $createChallenge
    ): JsonResponse {
        $this->authorize('view', $group);

        $challenge = $createChallenge($request->user(), $group, $request->validated());

        return ChallengeResource::make($challenge->load('participants')->loadCount('participants'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Challenge $challenge): ChallengeResource
    {
        $this->authorize('view', $challenge);

        return ChallengeResource::make(
            $challenge->load(['participants.user', 'participants.goal.stats'])
                ->loadCount('participants')
        );
    }

    public function join(
        JoinChallengeRequest $request,
        Challenge $challenge,
        JoinChallengeAction $joinChallenge
    ): ChallengeResource {
        $this->authorize('join', $challenge);

        $joinChallenge($request->user(), $challenge, $request->validated());

        return ChallengeResource::make(
            $challenge->load(['participants.user', 'participants.goal.stats'])
                ->loadCount('participants')
        );
    }

    public function leave(
        Request $request,
        Challenge $challenge,
        LeaveChallengeAction $leaveChallenge
    ): Response {
        $this->authorize('view', $challenge);

        $leaveChallenge($request->user(), $challenge);

        return response()->noContent();
    }

    public function destroy(Challenge $challenge): Response
    {
        $this->authorize('delete', $challenge);

        $challenge->delete();

        return response()->noContent();
    }
}
