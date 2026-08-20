<?php

namespace App\Actions\Roadmaps;

use App\Models\ActivityLog;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateRoadmapItemAction
{
    /**
     * FR-RM-04: items may be added all at once or one at a time, so an
     * absent `position` simply appends to the end of the roadmap.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(User $actor, Roadmap $roadmap, array $attributes): RoadmapItem
    {
        return DB::transaction(function () use ($actor, $roadmap, $attributes): RoadmapItem {
            if (! array_key_exists('position', $attributes) || $attributes['position'] === null) {
                $attributes['position'] = ((int) $roadmap->items()->max('position')) + 1;
            }

            $item = $roadmap->items()->create($attributes);

            ActivityLog::create([
                'user_id' => $actor->id,
                'subject_type' => RoadmapItem::class,
                'subject_id' => $item->id,
                'action' => 'roadmap_item.created',
                'meta' => ['title' => $item->title],
            ]);

            return $item;
        });
    }
}
