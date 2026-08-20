<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * FR-ADM-01, deliberately minimal: a handful of protected routes rather than
 * a full admin panel, which the closed-group nature of the app makes
 * sufficient.
 *
 * Admin is a genuine property of the account, unlike "mentor" — which is a
 * relationship and lives in `mentorships` (01 §4.7).
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(Response::HTTP_FORBIDDEN, 'Administrator access required.');
        }

        return $next($request);
    }
}
