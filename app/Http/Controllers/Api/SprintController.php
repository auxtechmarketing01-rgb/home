<?php

namespace App\Http\Controllers\Api;

use App\Actions\Sprints\CancelSprintAction;
use App\Actions\Sprints\CompleteSprintAction;
use App\Actions\Sprints\PauseSprintAction;
use App\Actions\Sprints\ResumeSprintAction;
use App\Actions\Sprints\StartSprintAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteSprintRequest;
use App\Http\Requests\IndexSprintRequest;
use App\Http\Requests\StartSprintRequest;
use App\Http\Resources\SprintResource;
use App\Models\Sprint;
use App\Services\SprintQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SprintController extends Controller
{
    /**
     * Scoped to the acting member inside SprintQueryService, not by a Policy
     * (02 §4).
     */
    public function index(IndexSprintRequest $request, SprintQueryService $sprints): AnonymousResourceCollection
    {
        return SprintResource::collection(
            $sprints->paginateForUser($request->user(), $request->validated())
        );
    }

    /**
     * The endpoint the SPA calls on bootstrap to recover a session started
     * before the browser was closed (FR-SPR-03). Returns `data: null` rather
     * than 404 when there is nothing running — "no active sprint" is a normal
     * state, not an error.
     */
    public function active(Request $request, SprintQueryService $sprints): JsonResponse
    {
        $sprint = $sprints->activeForUser($request->user());

        return response()->json([
            'data' => $sprint === null ? null : SprintResource::make($sprint)->resolve(),
        ]);
    }

    public function start(StartSprintRequest $request, StartSprintAction $startSprint): JsonResponse
    {
        $this->authorize('create', Sprint::class);

        $sprint = $startSprint($request->user(), $request->validated());

        return SprintResource::make($sprint->load(['goal', 'roadmapItem']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function pause(Sprint $sprint, PauseSprintAction $pauseSprint): SprintResource
    {
        $this->authorize('update', $sprint);

        return SprintResource::make($pauseSprint($sprint));
    }

    public function resume(Sprint $sprint, ResumeSprintAction $resumeSprint): SprintResource
    {
        $this->authorize('update', $sprint);

        return SprintResource::make($resumeSprint($sprint));
    }

    public function complete(
        CompleteSprintRequest $request,
        Sprint $sprint,
        CompleteSprintAction $completeSprint
    ): SprintResource {
        $this->authorize('update', $sprint);

        return SprintResource::make($completeSprint($sprint, $request->validated()));
    }

    public function cancel(Sprint $sprint, CancelSprintAction $cancelSprint): SprintResource
    {
        $this->authorize('update', $sprint);

        return SprintResource::make($cancelSprint($sprint));
    }

    /**
     * FR-SPR-06. Streamed and chunked so the response stays flat in memory
     * regardless of how much history the member has.
     */
    public function export(IndexSprintRequest $request, SprintQueryService $sprints): StreamedResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        return response()->streamDownload(function () use ($sprints, $user, $filters): void {
            $handle = fopen('php://output', 'w');
            $csv = Writer::createFromStream($handle);

            $csv->insertOne([
                'id',
                'started_at',
                'ended_at',
                'mode',
                'status',
                'planned_duration_seconds',
                'actual_duration_seconds',
                'paused_seconds_total',
                'goal',
                'roadmap_item',
                'notes',
            ]);

            $sprints->eachForExport($user, $filters, function (Sprint $sprint) use ($csv): void {
                $csv->insertOne([
                    $sprint->id,
                    $sprint->started_at?->toIso8601String(),
                    $sprint->ended_at?->toIso8601String(),
                    $sprint->mode,
                    $sprint->status,
                    $sprint->planned_duration_seconds,
                    $sprint->actual_duration_seconds,
                    $sprint->paused_seconds_total,
                    $sprint->goal?->title,
                    $sprint->roadmapItem?->title,
                    $sprint->notes,
                ]);
            });

            fclose($handle);
        }, 'sprints.csv', ['Content-Type' => 'text/csv']);
    }
}
