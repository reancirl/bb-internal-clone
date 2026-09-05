<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        $this->configureRateLimiters();
    }

    /**
     * Named limiters for the two public API endpoints (SEC-002).
     */
    private function configureRateLimiters(): void
    {
        // Mobile login. Keyed on email + IP so one attacker cannot lock out an
        // employee by hammering their address from elsewhere. Same 5/minute
        // budget the web login enforces in LoginRequest.
        RateLimiter::for('api-login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by(mb_strtolower($email).'|'.$request->ip());
        });

        // Website lead intake. Keyed on the calling token so a leaked token is
        // capped on its own, not against every other client's budget.
        RateLimiter::for('api-leads', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });
    }
}
