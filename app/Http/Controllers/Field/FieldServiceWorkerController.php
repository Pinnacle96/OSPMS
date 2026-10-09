<?php

namespace App\Http\Controllers\Field;

use App\Http\Controllers\Controller;

class FieldServiceWorkerController extends Controller
{
    public function __invoke()
    {
        return response()->file(public_path('pwa-field/sw.js'), ['Content-Type' => 'text/javascript; charset=UTF-8', 'Cache-Control' => 'no-cache', 'Service-Worker-Allowed' => '/field/', 'X-Content-Type-Options' => 'nosniff']);
    }
}
