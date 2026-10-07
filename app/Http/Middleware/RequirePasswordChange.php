<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('account.security', 'account.password.update', 'logout')) {
            return redirect()->route('account.security')->with('status', 'Set a new password before continuing.');
        }

        return $next($request);
    }
}
