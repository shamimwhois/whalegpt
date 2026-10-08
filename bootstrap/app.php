<?php

use App\Http\Middleware\EnsurePlan;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',

        then: function () {

            // Chat, workspace, history and media endpoints. The workspace is
            // scoped by a client-held id rather than a session, so the group
            // stays stateless. CSRF is not applied: these are called by fetch.
            Route::middleware('api')
                ->prefix('ai')
                ->group(base_path('routes/chat.php'));

            // The admin panel. Mounted under `web` so it shares the session
            // with the rest of the application, then gated by its own
            // allowlist middleware (see routes/admin.php).
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Aliases so a route reads as the rule it enforces rather than a class
        // name: ->middleware('plan:audio') and ->middleware('role:admin').
        $middleware->alias([
            'plan' => EnsurePlan::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('ai/*')
                || $request->expectsJson(),
        );
    })
    ->create();
