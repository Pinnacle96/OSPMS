<?php

namespace App\Http\Middleware;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Services\LoginActivityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->user()->status !== UserStatus::Active) {
            app(LoginActivityService::class)->record($request->user(), 'session_revoked', $request);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Inertia::clearHistory();

            return redirect()->route('login')->withErrors(['login' => 'Your account is not active. Contact your administrator.']);
        }

        return $next($request);
    }
}
