<?php

namespace App\Services;

use App\Models\Sprint;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read side of the sprint history (FR-SPR-06).
 *
 * Every query here starts from the acting member's own relation. `/sprints`
 * and `/sprints/export` carry no Policy precisely because they are scoped to
 * self (02 §4) — an empty Policy column means self-scoped, never unscoped,
 * and this is the one place that has to honour that.
 */
class SprintQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Sprint>
     */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->baseQuery($user, $filters)
            ->with(['goal', 'roadmapItem'])
            ->orderByDesc('started_at')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();
    }

    /**
     * The single running-or-paused sprint, if there is one. This is what the
     * SPA fetches on bootstrap to recover a session that was started before
     * the browser was closed (FR-SPR-03).
     */
    public function activeForUser(User $user): ?Sprint
    {
        return $user->sprints()
            ->active()
            ->with(['goal', 'roadmapItem'])
            ->latest('started_at')
            ->first();
    }

    /**
     * Streams the member's history in chunks rather than loading it all, so
     * the export stays flat in memory however long they have been using the
     * app.
     *
     * @param  array<string, mixed>  $filters
     * @param  Closure(Sprint): void  $callback
     */
    public function eachForExport(User $user, array $filters, Closure $callback): void
    {
        $this->baseQuery($user, $filters)
            ->with(['goal', 'roadmapItem'])
            ->orderBy('started_at')
            ->chunkById(500, function ($sprints) use ($callback): void {
                foreach ($sprints as $sprint) {
                    $callback($sprint);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Sprint>
     */
    protected function baseQuery(User $user, array $filters): Builder
    {
        $query = $user->sprints()->getQuery();

        if (($from = $filters['from'] ?? null) !== null) {
            $query->where('started_at', '>=', $this->startOfDayFor($user, $from));
        }

        if (($to = $filters['to'] ?? null) !== null) {
            $query->where('started_at', '<=', $this->endOfDayFor($user, $to));
        }

        if (($goalId = $filters['goal_id'] ?? null) !== null) {
            $query->where('goal_id', $goalId);
        }

        if (($itemId = $filters['roadmap_item_id'] ?? null) !== null) {
            $query->where('roadmap_item_id', $itemId);
        }

        if (($status = $filters['status'] ?? null) !== null) {
            $query->where('status', $status);
        }

        return $query;
    }

    /**
     * A member filtering "from the 3rd" means the 3rd where they live. The
     * boundary is resolved in their timezone and compared in UTC, the same
     * rule the streak arithmetic follows.
     */
    protected function startOfDayFor(User $user, string $date): string
    {
        return CarbonImmutable::parse($date, $user->timezoneName())
            ->startOfDay()
            ->utc()
            ->toDateTimeString();
    }

    protected function endOfDayFor(User $user, string $date): string
    {
        return CarbonImmutable::parse($date, $user->timezoneName())
            ->endOfDay()
            ->utc()
            ->toDateTimeString();
    }
}
