<?php

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Services\LoginActivityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\RevokeSessionsRequest;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function profile(Request $request)
    {
        return Inertia::render('Account/Profile', ['profile' => $request->user()->only('name', 'email', 'username', 'phone')]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $data = $request->validated();
        if (array_key_exists('email', $data) && $data['email'] !== $request->user()->email) {
            $request->user()->email_verified_at = null;
        }
        $request->user()->fill($data)->save();

        return back()->with('success', 'Profile updated.');
    }

    public function security(Request $request)
    {
        return Inertia::render('Account/Security', ['sessions' => DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get()->map(fn ($session) => [
            'ip_address' => $session->ip_address, 'user_agent' => $session->user_agent,
            'last_active_at' => Carbon::createFromTimestampUTC($session->last_activity)->toIso8601String(), 'is_current' => $session->id === $request->session()->getId(),
        ])]);
    }

    public function password(UpdatePasswordRequest $request, LoginActivityService $activity)
    {
        DB::transaction(function () use ($request, $activity) {
            $request->user()->forceFill(['password' => $request->validated('password'), 'remember_token' => Str::random(60), 'must_change_password' => false])->save();
            DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
            $activity->record($request->user(), 'password_reset', $request);
        });
        $request->session()->regenerate();

        return back()->with('success', 'Password changed. Other sessions have been revoked.');
    }

    public function revoke(RevokeSessionsRequest $request, LoginActivityService $activity)
    {
        Auth::logoutOtherDevices($request->validated('current_password'));
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        $activity->record($request->user(), 'session_revoked', $request);

        return back()->with('success', 'Other sessions have been revoked.');
    }

    public function access()
    {
        return Inertia::render('Account/Access');
    }
}
