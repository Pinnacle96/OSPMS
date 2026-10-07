<?php

use App\Domains\System\Services\PublicSystemConfig;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequirePasswordChange;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->alias([
            'active_user' => EnsureUserIsActive::class,
            'password_change' => RequirePasswordChange::class,
        ]);
        $middleware->redirectUsersTo(fn ($request) => route('account.access'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();
            if (! $request->expectsJson() && in_array($status, [403, 404, 419, 500, 503])) {
                $page = $status === 503 ? 'Maintenance' : (string) $status;

                return Inertia::render('Errors/'.$page, ['status' => $status, 'system' => app(PublicSystemConfig::class)->get()])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
