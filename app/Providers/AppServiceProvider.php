<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
        $this->registerSearchMacros();
    }

    /**
     * Case-insensitive "contains" search (BUG-003).
     *
     * `like` is case-sensitive on PostgreSQL — the production database — so a
     * search for "bloedorn" missed "Bloedorn". SQLite, which the tests use, is
     * case-insensitive for ASCII, which is why nothing caught it. Lowering both
     * sides behaves the same on every driver.
     *
     * Usage mirrors where()/orWhere():
     *   $q->whereLike('name', $search)->orWhereLike('notes', $search);
     */
    private function registerSearchMacros(): void
    {
        $bind = function (string $column, string $search, string $boolean) {
            /** @var EloquentBuilder|QueryBuilder $this */
            return $this->whereRaw(
                'LOWER('.$this->getGrammar()->wrap($column).') LIKE ?',
                ['%'.mb_strtolower(trim($search)).'%'],
                $boolean,
            );
        };

        foreach ([EloquentBuilder::class, QueryBuilder::class] as $builder) {
            $builder::macro('whereLike', function (string $column, string $search) use ($bind) {
                return $bind->call($this, $column, $search, 'and');
            });

            $builder::macro('orWhereLike', function (string $column, string $search) use ($bind) {
                return $bind->call($this, $column, $search, 'or');
            });
        }
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
