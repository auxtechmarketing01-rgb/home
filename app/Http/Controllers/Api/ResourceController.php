<?php

namespace App\Http\Controllers\Api;

use App\Actions\Resources\CreateResourceFileAction;
use App\Actions\Resources\DeleteResourceFileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResourceRequest;
use App\Http\Resources\ResourceFileResource;
use App\Models\Goal;
use App\Models\ResourceFile;
use App\Models\RoadmapItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * FR-RES-01/02. Attachments hang off either a Goal or a RoadmapItem, and both
 * parents authorize identically: `view` to list, `update` to attach. Reading
 * is therefore available to anyone who can see the parent — which from Phase 3
 * includes group members, and from Phase 4 an accepted mentor — while
 * attaching stays with whoever can edit it.
 */
class ResourceController extends Controller
{
    public function index(Goal $goal): AnonymousResourceCollection
    {
        $this->authorize('view', $goal);

        return $this->listFor($goal);
    }

    public function store(
        StoreResourceRequest $request,
        Goal $goal,
        CreateResourceFileAction $createResource
    ): JsonResponse {
        $this->authorize('update', $goal);

        return $this->attachTo($request, $goal, $createResource);
    }

    public function indexForItem(RoadmapItem $item): AnonymousResourceCollection
    {
        $this->authorize('view', $item);

        return $this->listFor($item);
    }

    public function storeForItem(
        StoreResourceRequest $request,
        RoadmapItem $item,
        CreateResourceFileAction $createResource
    ): JsonResponse {
        $this->authorize('update', $item);

        return $this->attachTo($request, $item, $createResource);
    }

    public function destroy(
        Request $request,
        ResourceFile $resource,
        DeleteResourceFileAction $deleteResource
    ): Response {
        $this->authorize('delete', $resource);

        $deleteResource($request->user(), $resource);

        return response()->noContent();
    }

    protected function listFor(Goal|RoadmapItem $parent): AnonymousResourceCollection
    {
        $resources = $parent->resourceFiles()
            ->with('uploader')
            ->latest('id')
            ->get();

        return ResourceFileResource::collection($resources);
    }

    protected function attachTo(
        StoreResourceRequest $request,
        Goal|RoadmapItem $parent,
        CreateResourceFileAction $createResource
    ): JsonResponse {
        $resource = $createResource(
            $request->user(),
            $parent,
            $request->safe()->except('file'),
            $request->file('file'),
        );

        return ResourceFileResource::make($resource)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
