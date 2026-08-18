<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('login', function (Request $request) {
            $identifier = Str::lower(trim((string) $request->input('login')));
            $ipAddress = (string) $request->ip();

            return [
                Limit::perMinute(60)->by("login-ip:{$ipAddress}"),
                Limit::perMinute(5)->by('login-identifier:'.hash('sha256', "{$identifier}|{$ipAddress}")),
            ];
        });
    }
}
