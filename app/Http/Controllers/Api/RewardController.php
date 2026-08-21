<?php

namespace App\Http\Controllers\Api;

use App\Actions\Rewards\ClaimRewardAction;
use App\Actions\Rewards\CreateRewardAction;
use App\Actions\Rewards\FulfillRewardAction;
use App\Actions\Rewards\RequestRewardAction;
use App\Actions\Rewards\RespondToRewardRequestAction;
use App\Actions\Rewards\RevokeRewardAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\FulfillRewardRequest;
use App\Http\Requests\IndexRewardRequest;
use App\Http\Requests\RequestRewardRequest;
use App\Http\Requests\RespondToRewardRequest;
use App\Http\Requests\StoreRewardRequest;
use App\Http\Resources\RewardResource;
use App\Models\Mentorship;
use App\Models\Reward;
use App\Services\RewardLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * FR-RWD-01..07. One route per transition, each authorizing its own ability
 * (02 §5) — there is deliberately no general "update a reward" endpoint.
 */
class RewardController extends Controller
{
    public function index(IndexRewardRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Reward::class);

        $user = $request->user();
        $filters = $request->validated();

        $query = Reward::query()
            ->forUser($user)
            ->with(['mentorship.mentor', 'mentorship.mentee', 'goal', 'roadmapItem']);

        if (($role = $filters['role'] ?? null) !== null) {
            $query->whereIn('mentorship_id', Mentorship::query()
                ->where($role === 'mentor' ? 'mentor_id' : 'mentee_id', $user->id)
                ->select('id'));
        }

        if (($status = $filters['status'] ?? null) !== null) {
            $query->withStatus($status);
        }

        if (($mentorshipId = $filters['mentorship_id'] ?? null) !== null) {
            $query->where('mentorship_id', $mentorshipId);
        }

        return RewardResource::collection(
            $query->latest('id')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString()
        );
    }

    /**
     * FR-RWD-01: mentor offers.
     */
    public function store(StoreRewardRequest $request, CreateRewardAction $createReward): JsonResponse
    {
        $mentorship = Mentorship::query()->findOrFail($request->validated()['mentorship_id']);

        $this->authorize('create', [Reward::class, $mentorship]);

        $reward = $createReward($request->user(), $mentorship, $request->validated());

        return RewardResource::make($reward->load('mentorship'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * FR-RWD-03: mentee demands something not yet offered.
     */
    public function request(RequestRewardRequest $request, RequestRewardAction $requestReward): JsonResponse
    {
        $mentorship = Mentorship::query()->findOrFail($request->validated()['mentorship_id']);

        $this->authorize('request', [Reward::class, $mentorship]);

        $reward = $requestReward($request->user(), $mentorship, $request->validated());

        return RewardResource::make($reward->load('mentorship'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function respond(
        RespondToRewardRequest $request,
        Reward $reward,
        RespondToRewardRequestAction $respond
    ): RewardResource {
        $this->authorize('respond', $reward);

        $validated = $request->validated();

        return RewardResource::make(
            $respond($request->user(), $reward, (bool) $validated['accepted'], $validated['note'] ?? null)
                ->load('mentorship')
        );
    }

    public function claim(Request $request, Reward $reward, ClaimRewardAction $claimReward): RewardResource
    {
        $this->authorize('claim', $reward);

        return RewardResource::make($claimReward($request->user(), $reward)->load('mentorship'));
    }

    public function fulfill(
        FulfillRewardRequest $request,
        Reward $reward,
        FulfillRewardAction $fulfillReward
    ): RewardResource {
        $this->authorize('fulfill', $reward);

        return RewardResource::make(
            $fulfillReward($request->user(), $reward, $request->validated()['note'] ?? null)
                ->load('mentorship')
        );
    }

    public function revoke(
        FulfillRewardRequest $request,
        Reward $reward,
        RevokeRewardAction $revokeReward
    ): RewardResource {
        $this->authorize('revoke', $reward);

        return RewardResource::make(
            $revokeReward($request->user(), $reward, $request->validated()['note'] ?? null)
                ->load('mentorship')
        );
    }

    /**
     * FR-RWD-06: a read-only summary of monetary rewards actually delivered,
     * per mentorship, so a parent does not have to remember what they have
     * settled.
     *
     * Only `fulfilled` rewards count. An `earned` or `claimed` monetary
     * reward is still a promise, and showing it here would read as a debt
     * already paid.
     *
     * **This is not a wallet.** Nothing in the response can be spent inside
     * the app, and the SPA is expected to label it as a record (01 NFR
     * Financial integrity).
     */
    public function ledger(Request $request, RewardLedgerService $ledger): JsonResponse
    {
        return response()->json(['data' => $ledger->forUser($request->user())]);
    }
}
