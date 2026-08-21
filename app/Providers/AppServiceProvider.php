<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureJobFailureLogging();
        $this->configureEmailedLinks();
    }

    /**
     * Emailed links must land on the SPA, not on the API.
     *
     * Laravel's default ResetPassword notification builds its URL from a route
     * named `password.reset`, which does not exist in a headless API — the
     * mail would carry a link to nowhere and FR-AUTH-03 would be broken in
     * exactly the half nobody tests (the token logic works fine either way).
     *
     * Email verification is the mirror image and deliberately different: that
     * link points at the *API*, because the signature Laravel puts on it has
     * to be verified by the app that issued it. The API then redirects to the
     * SPA (AuthController::verifyEmail).
     */
    protected function configureEmailedLinks(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $frontend = rtrim((string) config('app.frontend_url'), '/');

            return $frontend.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });
    }

    /**
     * Named rate limiters. `auth` and `sprint-start` are required by
     * 04 Cross-cutting / 01 NFR Security; `api` is the general envelope
     * applied to the whole API group in bootstrap/app.php.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return [
                Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('sprint-start', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Structured logging on job failures (04 Cross-cutting, 01 NFR
     * Observability). Horizon surfaces the failure in its dashboard; this
     * guarantees it also reaches the log channel even without Horizon.
     */
    protected function configureJobFailureLogging(): void
    {
        Queue::failing(function (JobFailed $event): void {
            Log::error('queue.job_failed', [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job' => $event->job->resolveName(),
                'attempts' => $event->job->attempts(),
                'exception' => $event->exception->getMessage(),
                'exception_class' => $event->exception::class,
            ]);
        });
    }
}
