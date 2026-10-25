<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'admin.token' => \App\Http\Middleware\EnsureAdminToken::class,
        ]);
        // The stats beacon is sent with navigator.sendBeacon and only records a page view
        $middleware->validateCsrfTokens(except: ['t']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The mobile app always gets JSON errors (validation, not found, too many requests)
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
    })->create();
