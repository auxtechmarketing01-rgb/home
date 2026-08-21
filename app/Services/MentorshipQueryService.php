<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\Mentorship;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read side of mentorship (02 §4's "controllers stay thin").
 *
 * The dashboard query lived in MentorshipController until an audit pointed out
 * that it was building an Eloquent chain in a controller body *and*
 * hand-rolling a visibility rule — `where('mentor_id', $user->id)` — instead of
 * going through `Goal::scopeVisibleTo`, which is the query-side mirror of
 * `GoalPolicy::view` (02 §5). It was not leaking anything, but it was a second
 * visibility path, and a second path is how the two eventually disagree.
 */
class MentorshipQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Mentorship>
     */
    public function forUser(User $user, array $filters = []): Collection
    {
        $query = Mentorship::query()->involving($user)->with(['mentor', 'mentee']);

        if (($role = $filters['role'] ?? null) !== null) {
            $query->where($role === 'mentor' ? 'mentor_id' : 'mentee_id', $user->id);
        }

        if (($status = $filters['status'] ?? null) !== null) {
            $query->where('status', $status);
        }

        return $query->latest('id')->get();
    }

    /**
     * The mentor dashboard from 01 §6.1: every mentee's progress on one screen,
     * so a parent with three children does not open each goal in turn.
     *
     * Reads the `goal_stats` cache and the per-member `streaks` row — no live
     * aggregation (02 §7).
     *
     * @return list<array<string, mixed>>
     */
    public function dashboardFor(User $mentor): array
    {
        $mentorships = Mentorship::query()
            ->accepted()
            ->where('mentor_id', $mentor->id)
            ->with(['mentee.streak'])
            ->get();

        if ($mentorships->isEmpty()) {
            return [];
        }

        /**
         * Scoped through the shared visibility scope rather than by mentee id.
         * It resolves to the same rows here — the mentorship branch of
         * `scopeVisibleTo` is exactly what grants this access — but it means
         * there is one definition of "what may a mentor see", not two.
         */
        $goals = Goal::query()
            ->visibleTo($mentor)
            ->whereIn('user_id', $mentorships->pluck('mentee_id'))
            ->with('stats')
            ->withCount('roadmapItems')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('user_id');

        return $mentorships->map(function (Mentorship $mentorship) use ($goals): array {
            $mentee = $mentorship->mentee;

            return [
                'mentorship_id' => $mentorship->id,
                'mentee' => ['id' => $mentee->id, 'name' => $mentee->name],
                'current_streak' => (int) ($mentee->streak?->current_streak ?? 0),
                'longest_streak' => (int) ($mentee->streak?->longest_streak ?? 0),
                'goals' => ($goals[$mentee->id] ?? collect())
                    ->map(fn (Goal $goal): array => [
                        'id' => $goal->id,
                        'title' => $goal->title,
                        'status' => $goal->status,
                        'roadmap_item_count' => $goal->roadmap_items_count,
                        'completion_percentage' => (float) ($goal->stats->completion_percentage ?? 0),
                        'total_focus_seconds' => (int) ($goal->stats->total_focus_seconds ?? 0),
                        'projected_completion_date' => $goal->stats?->projected_completion_date?->toDateString(),
                    ])->values()->all(),
            ];
        })->all();
    }
}
