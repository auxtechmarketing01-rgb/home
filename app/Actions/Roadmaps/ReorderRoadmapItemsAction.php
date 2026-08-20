<?php

namespace App\Actions\Roadmaps;

use App\Models\ActivityLog;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderRoadmapItemsAction
{
    /**
     * FR-RM-05: one transaction for the whole batch, so a half-applied
     * order can never be observed.
     *
     * The cross-roadmap check is repeated here even though
     * ReorderRoadmapItemsRequest scopes its `exists` rule to the same
     * roadmap: this Action is also called directly in tests and must not
     * depend on a Form Request having run first (04 Phase 1).
     *
     * @param  list<array{id: int, position: int}>  $items
     * @return Collection<int, RoadmapItem>
     */
    public function __invoke(User $actor, Roadmap $roadmap, array $items): Collection
    {
        return DB::transaction(function () use ($actor, $roadmap, $items): Collection {
            $requestedIds = array_map(static fn (array $row): int => (int) $row['id'], $items);

            $ownedIds = $roadmap->items()->whereKey($requestedIds)->pluck('id')->all();

            $foreignIds = array_values(array_diff($requestedIds, $ownedIds));

            if ($foreignIds !== []) {
                throw ValidationException::withMessages([
                    'items' => 'Every roadmap item must belong to this roadmap.',
                ]);
            }

            $timestamp = now();

            foreach ($items as $row) {
                DB::table('roadmap_items')
                    ->where('roadmap_id', $roadmap->id)
                    ->where('id', (int) $row['id'])
                    ->update([
                        'position' => (int) $row['position'],
                        'updated_at' => $timestamp,
                    ]);
            }

            ActivityLog::create([
                'user_id' => $actor->id,
                'subject_type' => Roadmap::class,
                'subject_id' => $roadmap->id,
                'action' => 'roadmap.reordered',
                'meta' => ['item_count' => count($items)],
            ]);

            return $roadmap->items()->orderBy('position')->orderBy('id')->get();
        });
    }
}
