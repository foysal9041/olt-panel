<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

    $middleware->alias([
        'admin' => \App\Http\Middleware\AdminMiddleware::class,
    ]);

    // The ZKTeco F18 pushes attendance data over plain HTTP without a
    // Laravel session/CSRF token, so its endpoints must be exempt.
    $middleware->validateCsrfTokens(except: [
        'iclock/*',
    ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
