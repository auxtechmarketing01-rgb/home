<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * FR-ADM-01: an admin can disable an abusive account.
 *
 * Rejecting at login alone is not enough — a member disabled mid-session
 * would keep working until their cookie expired. This runs on every
 * authenticated API request and tears the session down on the spot.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isDisabled()) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json([
                'message' => 'This account has been disabled.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
