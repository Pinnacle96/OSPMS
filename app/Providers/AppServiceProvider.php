<?php

namespace App\Providers;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Assignments\Policies\AssignmentPolicy;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Drivers\Policies\DriverPolicy;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Policies\FinancialTransactionPolicy;
use App\Domains\Geography\Models\Lga;
use App\Domains\Geography\Policies\LgaPolicy;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Policies\RolePolicy;
use App\Domains\Identity\Policies\UserPolicy;
use App\Domains\Operators\Models\Operator;
use App\Domains\Operators\Policies\OperatorPolicy;
use App\Domains\Parks\Models\Park;
use App\Domains\Parks\Policies\ParkPolicy;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\Models\Receipt;
use App\Domains\Payments\Policies\PaymentPolicy;
use App\Domains\Payments\Policies\ReceiptPolicy;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Revenue\Policies\FeeConfigurationPolicy;
use App\Domains\Revenue\Policies\RevenueHeadPolicy;
use App\Domains\Routes\Models\Route;
use App\Domains\Routes\Policies\RoutePolicy;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Policies\TicketPolicy;
use App\Domains\Vehicles\Models\Vehicle;
use App\Domains\Vehicles\Policies\VehiclePolicy;
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
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Receipt::class, ReceiptPolicy::class);
        Gate::policy(FinancialTransaction::class, FinancialTransactionPolicy::class);
        RateLimiter::for('receipt-verification', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        Gate::policy(Ticket::class, TicketPolicy::class);
        RateLimiter::for('ticket-verification', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Operator::class, OperatorPolicy::class);
        Gate::policy(Driver::class, DriverPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(DriverAssignment::class, AssignmentPolicy::class);
        Gate::policy(RevenueHead::class, RevenueHeadPolicy::class);
        Gate::policy(FeeConfiguration::class, FeeConfigurationPolicy::class);
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
