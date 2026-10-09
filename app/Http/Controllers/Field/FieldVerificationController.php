<?php

namespace App\Http\Controllers\Field;

use App\Domains\Enforcement\Services\FieldVerificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FieldVerificationController extends Controller
{
    public function scan()
    {
        return Inertia::render('Field/Scan', ['verification_origin' => rtrim(config('app.url'), '/')]);
    }

    public function show(Request $request, string $token, FieldVerificationService $service)
    {
        $input = $request->validate(['kind' => 'nullable|in:ticket,receipt']);
        $result = $service->verify($request->user(), $token, $input['kind'] ?? 'ticket');
        abort_unless($result, 404);

        return Inertia::render('Field/VerifyResult', $result)->toResponse($request)->withHeaders(['Referrer-Policy' => 'no-referrer']);
    }
}
