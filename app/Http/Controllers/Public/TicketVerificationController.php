<?php

namespace App\Http\Controllers\Public;

use App\Domains\Ticketing\Services\TicketVerificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketVerificationController extends Controller
{
    public function __invoke(Request $request, string $token, TicketVerificationService $service)
    {
        $safe = $service->verify($token);

        return Inertia::render('Public/TicketVerification', ['verification' => $safe])->toResponse($request)
            ->setStatusCode($safe ? 200 : 404)->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
