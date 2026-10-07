<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\LoginActivity;
use App\Domains\Identity\Models\User;
use Illuminate\Http\Request;

class LoginActivityService
{
    public function record(User $user, string $event, Request $request): void
    {
        LoginActivity::create([
            'user_id' => $user->id,
            // Hash the session identifier; never retain a reusable session token.
            'session_identifier' => $request->hasSession() ? hash('sha256', $request->session()->getId()) : null,
            'event' => $event, 'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000), 'occurred_at' => now(),
        ]);
        activity('authentication')->causedBy($user)->performedOn($user)->log($event);
    }
}
