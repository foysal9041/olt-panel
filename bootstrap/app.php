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

    // "viewer" role: read-only everywhere.
    $middleware->appendToGroup('web', \App\Http\Middleware\ViewerReadOnly::class);

    // The site is served through Cloudflare (noc.sunlitnetwork.com). Trust
    // only Cloudflare's edge ranges, so request()->ip() is the real visitor
    // and HTTPS is detected from X-Forwarded-Proto — a direct hit on the
    // server can't spoof these headers.
    $middleware->trustProxies(
        at: [
            '127.0.0.1',
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
            '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
        ],
        headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
    );

    // The ZKTeco F18 pushes attendance data over plain HTTP without a
    // Laravel session/CSRF token, so its endpoints must be exempt.
    $middleware->validateCsrfTokens(except: [
        'iclock/*',
    ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
