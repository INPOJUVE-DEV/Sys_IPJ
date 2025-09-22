<?php

namespace App\Providers;

use App\Models\ComponentCatalog;
use App\Models\Page;
use App\Models\Salud360Assignment;
use App\Models\Salud360Session;
use App\Models\Theme;
use App\Policies\ComponentCatalogPolicy;
use App\Policies\PagePolicy;
use App\Policies\Salud360AssignmentPolicy;
use App\Policies\Salud360SessionPolicy;
use App\Policies\ThemePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute((int) config('app.rate_limits.public', 60))
                ->by($request->ip());
        });

        Gate::policy(Salud360Assignment::class, Salud360AssignmentPolicy::class);
        Gate::policy(Salud360Session::class, Salud360SessionPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(ComponentCatalog::class, ComponentCatalogPolicy::class);
        Gate::policy(Theme::class, ThemePolicy::class);
    }
}