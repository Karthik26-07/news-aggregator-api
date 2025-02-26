<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
class RouteServiceProvider extends ServiceProvider
{


    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });
        // Rate limiter for /login
        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Rate limiter for /password/email
        RateLimiter::for('password-email', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Rate limiter for /password/reset (POST)
        RateLimiter::for('password-reset', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Rate limiter for /password/reset (GET)
        RateLimiter::for('password-reset-view', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Rate limiter for /email/verification/resend
        RateLimiter::for('verification-resend', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Rate limiter for /email/verify/{id}/{hash}
        RateLimiter::for('verification-verify', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
