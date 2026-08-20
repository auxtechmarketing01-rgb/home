<?php

namespace App\Http\Controllers\Api;

use App\Actions\Goals\ArchiveGoalAction;
use App\Actions\Goals\CompleteGoalAction;
use App\Actions\Goals\CreateGoalAction;
use App\Actions\Goals\UpdateGoalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexGoalRequest;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Http\Resources\GoalCollection;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use App\Services\GoalQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GoalController extends Controller
{
    public function index(IndexGoalRequest $request, GoalQueryService $goals): GoalCollection
    {
        $this->authorize('viewAny', Goal::class);

        return new GoalCollection(
            $goals->paginateVisibleTo($request->user(), $request->validated())
        );
    }

    public function store(StoreGoalRequest $request, CreateGoalAction $createGoal): JsonResponse
    {
        $this->authorize('create', Goal::class);

        $goal = $createGoal($request->user(), $request->validated());

        return GoalResource::make($goal->load(['category', 'roadmap']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Goal $goal, GoalQueryService $goals): GoalResource
    {
        $this->authorize('view', $goal);

        return GoalResource::make($goals->loadForShow($goal));
    }

    public function update(UpdateGoalRequest $request, Goal $goal, UpdateGoalAction $updateGoal): GoalResource
    {
        $this->authorize('update', $goal);

        $goal = $updateGoal($request->user(), $goal, $request->validated());

        return GoalResource::make($goal->load('category'));
    }

    /**
     * FR-GOAL-03: archiving is a soft delete.
     */
    public function destroy(Request $request, Goal $goal, ArchiveGoalAction $archiveGoal): Response
    {
        $this->authorize('delete', $goal);

        $archiveGoal($request->user(), $goal);

        return response()->noContent();
    }

    /**
     * FR-GOAL-04: the user confirms completion; nothing completes a goal on
     * their behalf.
     */
    public function complete(Request $request, Goal $goal, CompleteGoalAction $completeGoal): GoalResource
    {
        $this->authorize('update', $goal);

        $goal = $completeGoal($request->user(), $goal);

        return GoalResource::make($goal->load('category'));
    }
}
