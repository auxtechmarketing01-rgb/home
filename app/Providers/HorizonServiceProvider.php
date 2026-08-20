<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Horizon runs against the `redis` queue connection used in deploy (see
 * `config/horizon.php` supervisor-1). It cannot run on this Windows dev
 * machine — the package needs `ext-pcntl`/`ext-posix`, which do not exist
 * there, and no Redis server is running locally. Local work uses the
 * `database` queue driver instead; nothing in the application code depends on
 * which of the two is active.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        /**
         * 01 NFR Observability: a failed stats recalculation or reward
         * notification must be noticed, not silently retried forever. Mail
         * routing is opt-in via env so a missing address never breaks boot;
         * AppServiceProvider also logs every failure to the log channel
         * regardless, so failures are never invisible.
         */
        if ($address = config('pathforge.horizon.notify_email')) {
            Horizon::routeMailNotificationsTo($address);
        }
    }

    /**
     * Who may open the Horizon dashboard outside `local`.
     *
     * Config-driven rather than a hardcoded array, so granting access in
     * deploy is an env change rather than a code change — and the default of
     * "nobody" means an un-configured production deployment cannot expose the
     * queue dashboard by accident.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user): bool {
            if ($user === null) {
                return false;
            }

            $allowed = array_filter(array_map(
                'trim',
                explode(',', (string) config('pathforge.horizon.allowed_emails'))
            ));

            return in_array($user->email, $allowed, true);
        });
    }
}
