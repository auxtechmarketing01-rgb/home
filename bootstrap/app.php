<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    /**
     * The private-channel authorization endpoint is deliberately mounted
     * inside the versioned, stateful API group rather than on the default
     * `web` group: the SPA is a separate origin, so it must authorize
     * channels with the same Sanctum session cookie and CORS rules it uses
     * for every other call (02 §1, §10). Result: POST /api/v1/broadcasting/auth.
     */
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        /**
         * `active` is here as well as on the API route group: this endpoint
         * hands out the credential that lets a client subscribe to a private
         * channel, so a disabled account must not get one — otherwise it
         * would keep receiving live notifications after being locked out
         * (FR-ADM-01).
         */
        attributes: ['prefix' => 'api/v1', 'middleware' => ['api', 'auth:sanctum', 'active']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * Sanctum SPA cookie auth: the API group becomes stateful for the
         * configured first-party domains (02 §1, FR-AUTH-02).
         */
        $middleware->statefulApi();

        $middleware->throttleApi('api');

        /**
         * Applied as route middleware inside the `auth:sanctum` group rather
         * than to the whole api group, so the authenticated member is always
         * resolved by the time they run (FR-ADM-01).
         */
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
