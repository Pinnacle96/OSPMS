<?php

namespace App\Http\Controllers\Web\Auth;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\LoginActivityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PasswordResetController extends Controller
{
    public function forgot()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function send(ForgotPasswordRequest $request)
    {
        Password::sendResetLink($request->validated());

        return back()->with('status', 'If an account uses that email address, a password reset link has been sent.');
    }

    public function reset(Request $request, string $token)
    {
        return Inertia::render('Auth/ResetPassword', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(ResetPasswordRequest $request, LoginActivityService $activity)
    {
        $status = Password::reset($request->validated(), function (User $user, string $password) use ($activity, $request) {
            DB::transaction(function () use ($user, $password, $activity, $request) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'must_change_password' => false])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $activity->record($user, 'password_reset', $request);
            });
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
