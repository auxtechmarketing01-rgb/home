<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read-side query building for goals. It lives here rather than in
 * GoalController so the controller stays `validate -> call -> Resource`
 * (02 §4), and so the visibility scope is applied in exactly one place.
 */
class GoalQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Goal>
     */
    public function paginateVisibleTo(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Goal::query()
            ->visibleTo($user)
            ->with(['category', 'user', 'stats'])
            ->withCount('roadmapItems');

        if (($status = $filters['status'] ?? null) !== null) {
            $query->where('goals.status', $status);
        }

        if (($categoryId = $filters['category_id'] ?? null) !== null) {
            $query->where('goals.category_id', $categoryId);
        }

        if (($visibility = $filters['visibility'] ?? null) !== null) {
            $query->where('goals.visibility', $visibility);
        }

        if (($search = $filters['search'] ?? null) !== null) {
            $query->where('goals.title', 'like', '%'.$search.'%');
        }

        return $query
            ->orderByDesc('goals.created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    /**
     * The single-goal read used by `GET /goals/{goal}`: the roadmap and its
     * ordered items in one round trip, no N+1 (01 NFR Performance).
     */
    public function loadForShow(Goal $goal): Goal
    {
        return $goal->load([
            'category',
            'user',
            'stats',
            'roadmap' => fn ($query) => $query->withCount('items'),
            'roadmap.items' => fn ($query) => $query->orderBy('position')->orderBy('id'),
            'roadmap.items.children' => fn ($query) => $query->orderBy('position')->orderBy('id'),
        ]);
    }
}
