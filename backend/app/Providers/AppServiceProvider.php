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

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CloudinaryImageGateway::class, CloudinarySdkImageGateway::class);
    }

    public function boot(): void
    {
        // The Angular app accepts plain objects or { data: ... }; plain is simpler to read in the browser.
        JsonResource::withoutWrapping();

        // While tests run, a forgotten ->with(...) (the "N+1" slowdown) becomes an error instead of a slow page.
        Model::preventLazyLoading($this->app->environment('testing'));

        $this->defineRateLimits();
    }

    /**
     * Rate limits (the numbers are in config/actify.php). A limit is "N requests per period for one key";
     * going over answers 429 with a Retry-After header, which the Angular app shows as a friendly sentence.
     */
    private function defineRateLimits(): void
    {
        // Everything under /api: per signed-in person, or per IP address when signed out.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(config('actify.api_rate_per_minute'))
            ->by($request->user('sanctum')?->getAuthIdentifier() ?: $request->ip()));

        // Wrong-password guessing: per username AND address, so one attacker cannot lock out a real person elsewhere.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(config('actify.login_per_minute'))
            ->by(mb_strtolower((string) $request->input('username')).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(config('actify.register_per_hour'))
            ->by($request->ip()));

        // Changing a password needs the current one: stop guessing it from a stolen (still valid) token.
        RateLimiter::for('password', fn (Request $request) => Limit::perMinute(5)
            ->by($request->user('sanctum')?->getAuthIdentifier() ?: $request->ip()));

        // Picture uploads are the heaviest calls (the server re-encodes the picture).
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user('sanctum')?->getAuthIdentifier() ?: $request->ip()));

        // A burst guard in front of the database-based guest limits in GuestReportController.
        RateLimiter::for('guest-reports', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
    }
}
