<?php

namespace App\Http\Controllers\Web\Auth;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\LoginActivityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request, LoginActivityService $activity)
    {
        $login = mb_strtolower(trim($request->validated('login')));
        $field = str_contains($login, '@') ? 'email' : 'username';
        $user = User::where($field, $login)->first();
        // A fixed dummy hash keeps unknown-account password checks comparable to real checks.
        $validPassword = Hash::check($request->validated('password'), $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $user || ! $validPassword || $user->status !== UserStatus::Active) {
            if ($user) {
                $activity->record($user, 'login_failed', $request);
            } else {
                activity('authentication')->withProperties(['ip_address' => $request->ip()])->log('login_failed');
            }
            throw ValidationException::withMessages(['login' => 'These credentials do not match an active account.']);
        }
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $activity->record($user, 'login_success', $request);
        $destination = $user->must_change_password ? 'account.security' : ($user->can('view_state_dashboard') ? 'dashboard.state' : 'account.access');

        return redirect()->intended(route($destination));
    }

    public function destroy(Request $request, LoginActivityService $activity)
    {
        $activity->record($request->user(), 'logout', $request);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
