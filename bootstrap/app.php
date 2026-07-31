<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin.access' => \App\Http\Middleware\AdminAccessMiddleware::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        // Browsers POST these with no session/token context, so they can't
        // carry a CSRF token; the route is rate-limited instead (routes/web.php).
        $middleware->validateCsrfTokens(except: [
            'csp-reports',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
