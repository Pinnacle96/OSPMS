<?php

namespace App\Http\Controllers\Field;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FieldHomeController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->getPathInfo() === '/field') {
            return new RedirectResponse('/field/');
        }

        return Inertia::render('Field/Home');
    }
}
