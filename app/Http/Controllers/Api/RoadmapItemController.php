<?php

namespace App\Http\Controllers\Api;

use App\Actions\Roadmaps\AssignRoadmapItemAction;
use App\Actions\Roadmaps\CreateRoadmapItemAction;
use App\Actions\Roadmaps\DeleteRoadmapItemAction;
use App\Actions\Roadmaps\ReorderRoadmapItemsAction;
use App\Actions\Roadmaps\UpdateRoadmapItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignRoadmapItemRequest;
use App\Http\Requests\ReorderRoadmapItemsRequest;
use App\Http\Requests\StoreRoadmapItemRequest;
use App\Http\Requests\UpdateRoadmapItemRequest;
use App\Http\Resources\RoadmapItemResource;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RoadmapItemController extends Controller
{
    /**
     * Returns the roadmap flat and position-ordered. Nesting (FR-RM-03) is
     * expressed through `parent_id` so the Timeline and Kanban views
     * (FR-RM-06) can each group the same rows their own way.
     */
    public function index(Roadmap $roadmap): AnonymousResourceCollection
    {
        $this->authorize('view', $roadmap->goal);

        $items = $roadmap->items()
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return RoadmapItemResource::collection($items);
    }

    public function store(
        StoreRoadmapItemRequest $request,
        Roadmap $roadmap,
        CreateRoadmapItemAction $createItem
    ): JsonResponse {
        $this->authorize('update', $roadmap->goal);

        $item = $createItem($request->user(), $roadmap, $request->validated());

        return RoadmapItemResource::make($item)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdateRoadmapItemRequest $request,
        RoadmapItem $item,
        UpdateRoadmapItemAction $updateItem
    ): RoadmapItemResource {
        $this->authorize('update', $item);

        return RoadmapItemResource::make(
            $updateItem($request->user(), $item, $request->validated())
        );
    }

    public function destroy(
        Request $request,
        RoadmapItem $item,
        DeleteRoadmapItemAction $deleteItem
    ): Response {
        $this->authorize('delete', $item);

        $deleteItem($request->user(), $item);

        return response()->noContent();
    }

    /**
     * FR-MENT-05. Authorizes `assign`, which is a **different ability** from
     * `update` — that separation is what stops a mentor gaining edit rights
     * over the mentee's own content (FR-MENT-06). A mentor calling
     * `PUT /roadmap-items/{item}` still gets 403 after succeeding here.
     */
    public function assign(
        AssignRoadmapItemRequest $request,
        RoadmapItem $item,
        AssignRoadmapItemAction $assignItem
    ): RoadmapItemResource {
        $this->authorize('assign', $item);

        return RoadmapItemResource::make(
            $assignItem($request->user(), $item, $request->validated())
        );
    }

    /**
     * FR-RM-05: the whole batch lands in one transaction.
     */
    public function reorder(
        ReorderRoadmapItemsRequest $request,
        Roadmap $roadmap,
        ReorderRoadmapItemsAction $reorderItems
    ): AnonymousResourceCollection {
        $this->authorize('update', $roadmap->goal);

        $items = $reorderItems($request->user(), $roadmap, $request->validated()['items']);

        return RoadmapItemResource::collection($items);
    }
}
