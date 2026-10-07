<?php

namespace App\Providers;

use App\Domains\Geography\Models\Lga;
use App\Domains\Geography\Policies\LgaPolicy;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Policies\RolePolicy;
use App\Domains\Identity\Policies\UserPolicy;
use App\Domains\Parks\Models\Park;
use App\Domains\Parks\Policies\ParkPolicy;
use App\Domains\Routes\Models\Route;
use App\Domains\Routes\Policies\RoutePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Lga::class, LgaPolicy::class);
        Gate::policy(Park::class, ParkPolicy::class);
        Gate::policy(Route::class, RoutePolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('login')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
