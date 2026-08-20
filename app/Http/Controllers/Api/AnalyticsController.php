<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaderboardRequest;
use App\Http\Requests\OverviewRequest;
use App\Http\Resources\GoalStatsResource;
use App\Http\Resources\LeaderboardEntryResource;
use App\Models\Goal;
use App\Models\Group;
use App\Services\AnalyticsService;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnalyticsController extends Controller
{
    /**
     * FR-ANL-01. Reads the `goal_stats` cache row directly and never
     * recomputes aggregates live (02 §7). Absent until the first
     * RecalculateGoalStatsJob has run, which is reported as `data: null`
     * rather than a 404 — a goal with no logged time yet is normal.
     */
    public function goalStats(Goal $goal): JsonResponse
    {
        $this->authorize('view', $goal);

        $stats = $goal->stats;

        return response()->json([
            'data' => $stats === null ? null : GoalStatsResource::make($stats)->resolve(),
        ]);
    }

    /**
     * FR-GRP-03. Every aggregate is bounded to goals shared with this group;
     * see LeaderboardService for why the streak column is the one deliberate
     * exception.
     */
    public function leaderboard(
        LeaderboardRequest $request,
        Group $group,
        LeaderboardService $leaderboards
    ): AnonymousResourceCollection {
        $this->authorize('view', $group);

        $entries = $leaderboards->forGroup($group, $request->validated()['period'] ?? 'week');

        return LeaderboardEntryResource::collection($entries)->additional([
            'meta' => [
                'group_id' => $group->id,
                'period' => $request->validated()['period'] ?? 'week',
            ],
        ]);
    }

    /**
     * FR-ANL-03. No Policy — scoped to the acting member inside
     * AnalyticsService (02 §4).
     */
    public function overview(OverviewRequest $request, AnalyticsService $analytics): JsonResponse
    {
        return response()->json([
            'data' => $analytics->overviewFor(
                $request->user(),
                (int) ($request->validated()['trend_days'] ?? 28),
            ),
        ]);
    }
}
