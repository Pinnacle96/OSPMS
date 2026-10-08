<?php

namespace App\Http\Controllers\Public;

use App\Domains\Payments\Services\ReceiptVerificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReceiptVerificationController extends Controller
{
    public function __invoke(Request $request, string $token, ReceiptVerificationService $service)
    {
        $safe = $service->verify($token);

        return Inertia::render('Public/ReceiptVerification', ['verification' => $safe])->toResponse($request)->setStatusCode($safe ? 200 : 404)->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
