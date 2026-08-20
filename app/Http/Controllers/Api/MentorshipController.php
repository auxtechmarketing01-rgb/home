<?php

namespace App\Http\Controllers\Api;

use App\Actions\Mentorships\EndMentorshipAction;
use App\Actions\Mentorships\RequestMentorshipAction;
use App\Actions\Mentorships\RespondToMentorshipAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexMentorshipRequest;
use App\Http\Requests\RequestMentorshipRequest;
use App\Http\Resources\MentorshipResource;
use App\Models\Goal;
use App\Models\Mentorship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MentorshipController extends Controller
{
    /**
     * Scoped to relationships the acting member is part of, on either side
     * (02 §4). FR-MENT-03 allows many of each simultaneously, so `role`
     * filters the two directions apart.
     */
    public function index(IndexMentorshipRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Mentorship::class);

        $user = $request->user();
        $filters = $request->validated();

        $query = Mentorship::query()->involving($user)->with(['mentor', 'mentee']);

        if (($role = $filters['role'] ?? null) !== null) {
            $query->where($role === 'mentor' ? 'mentor_id' : 'mentee_id', $user->id);
        }

        if (($status = $filters['status'] ?? null) !== null) {
            $query->where('status', $status);
        }

        return MentorshipResource::collection($query->latest('id')->get());
    }

    /**
     * FR-MENT-01. The shared-group requirement is enforced inside
     * RequestMentorshipAction as a real authorization rule, so it holds for
     * every caller rather than only for requests that came through the Form
     * Request (02 §5).
     */
    public function store(
        RequestMentorshipRequest $request,
        RequestMentorshipAction $requestMentorship
    ): JsonResponse {
        $validated = $request->validated();
        $target = User::query()->findOrFail($validated['user_id']);

        $this->authorize('create', [Mentorship::class, $target]);

        $mentorship = $requestMentorship($request->user(), $target, $validated['role']);

        return MentorshipResource::make($mentorship->load(['mentor', 'mentee']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * FR-MENT-02: only the non-initiating party reaches here — the policy's
     * `respond` ability enforces it.
     */
    public function accept(
        Request $request,
        Mentorship $mentorship,
        RespondToMentorshipAction $respond
    ): MentorshipResource {
        $this->authorize('respond', $mentorship);

        return MentorshipResource::make(
            $respond($request->user(), $mentorship, true)->load(['mentor', 'mentee'])
        );
    }

    public function decline(
        Request $request,
        Mentorship $mentorship,
        RespondToMentorshipAction $respond
    ): MentorshipResource {
        $this->authorize('respond', $mentorship);

        return MentorshipResource::make(
            $respond($request->user(), $mentorship, false)->load(['mentor', 'mentee'])
        );
    }

    /**
     * FR-MENT-07: either party, at any time.
     */
    public function end(
        Request $request,
        Mentorship $mentorship,
        EndMentorshipAction $endMentorship
    ): MentorshipResource {
        $this->authorize('end', $mentorship);

        return MentorshipResource::make(
            $endMentorship($request->user(), $mentorship)->load(['mentor', 'mentee'])
        );
    }

    /**
     * The mentor dashboard from 01 §6.1: every mentee's progress on one
     * screen, so a parent with three children does not have to open each goal
     * in turn.
     *
     * Reads the `goal_stats` cache and the per-member `streaks` row — no live
     * aggregation, and nothing here that GoalPolicy::view would not already
     * grant this mentor.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $mentorships = Mentorship::query()
            ->accepted()
            ->where('mentor_id', $user->id)
            ->with(['mentee.streak'])
            ->get();

        $data = $mentorships->map(function (Mentorship $mentorship) {
            $mentee = $mentorship->mentee;

            $goals = Goal::query()
                ->where('user_id', $mentee->id)
                ->with('stats')
                ->withCount('roadmapItems')
                ->orderByDesc('created_at')
                ->get();

            return [
                'mentorship_id' => $mentorship->id,
                'mentee' => ['id' => $mentee->id, 'name' => $mentee->name],
                'current_streak' => (int) ($mentee->streak?->current_streak ?? 0),
                'longest_streak' => (int) ($mentee->streak?->longest_streak ?? 0),
                'goals' => $goals->map(fn (Goal $goal): array => [
                    'id' => $goal->id,
                    'title' => $goal->title,
                    'status' => $goal->status,
                    'roadmap_item_count' => $goal->roadmap_items_count,
                    'completion_percentage' => (float) ($goal->stats->completion_percentage ?? 0),
                    'total_focus_seconds' => (int) ($goal->stats->total_focus_seconds ?? 0),
                    'projected_completion_date' => $goal->stats?->projected_completion_date?->toDateString(),
                ])->all(),
            ];
        })->all();

        return response()->json(['data' => $data]);
    }
}
