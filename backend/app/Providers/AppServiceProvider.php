<?php

namespace App\Providers;

use App\Support\CloudinaryImageGateway;
use App\Support\CloudinarySdkImageGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

// Wires up the app when it starts: bindings, JSON shape and rate limits.
class AppServiceProvider extends ServiceProvider
{
    // Connects the image gateway interface to the real Cloudinary one.
    public function register(): void
    {
        $this->app->bind(CloudinaryImageGateway::class, CloudinarySdkImageGateway::class);
    }

    // Runs the startup setup: flat JSON resources, lazy-loading check and rate limits.
    public function boot(): void
    {

        JsonResource::withoutWrapping();


        Model::preventLazyLoading($this->app->environment('testing'));

        $this->defineRateLimits();
    }


    // Sets how many requests each kind of route may make per minute.
    private function defineRateLimits(): void
    {

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(config('actify.api_rate_per_minute'))
            ->by($request->user('sanctum')?->getAuthIdentifier() ?: $request->ip()));


        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(config('actify.login_per_minute'))
            ->by(mb_strtolower((string) $request->input('username')).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(config('actify.register_per_hour'))
            ->by($request->ip()));


        RateLimiter::for('password', fn (Request $request) => Limit::perMinute(5)
            ->by($request->user('sanctum')?->getAuthIdentifier() ?: $request->ip()));


        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user('sanctum')?->getAuthIdentifier() ?: $request->ip()));


        RateLimiter::for('guest-reports', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
    }
}
