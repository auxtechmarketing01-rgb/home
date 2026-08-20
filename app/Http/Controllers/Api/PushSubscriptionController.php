<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeletePushSubscriptionRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * FR-SPR-10's registration endpoints. Both bind to the authenticated member,
 * so a subscription can never be aimed at another account (02 §5).
 */
class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['contentEncoding'] ?? null,
        );

        return response()->json(['message' => 'Subscribed.'], Response::HTTP_CREATED);
    }

    /**
     * Called when the member turns notifications off client-side. Deleting an
     * endpoint that is not theirs is a no-op rather than an error: the
     * package scopes the delete to the subscribable.
     */
    public function destroy(DeletePushSubscriptionRequest $request): Response
    {
        $request->user()->deletePushSubscription($request->validated()['endpoint']);

        return response()->noContent();
    }
}
