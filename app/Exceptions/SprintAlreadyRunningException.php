<?php

namespace App\Exceptions;

use App\Models\Sprint;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * FR-SPR-08: a member may have at most one running-or-paused sprint.
 *
 * A dedicated exception rather than `abort(409)` for two reasons: the Action
 * is unit-tested directly (06 §1.3), where asserting on this class is far
 * more precise than catching a generic HTTP exception; and 409 is a
 * deliberate, documented conflict response, not an accident to be discovered
 * as a 500 in production.
 */
class SprintAlreadyRunningException extends Exception
{
    public function __construct(public readonly Sprint $activeSprint)
    {
        parent::__construct('You already have a sprint in progress.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'active_sprint_id' => $this->activeSprint->id,
        ], Response::HTTP_CONFLICT);
    }
}
