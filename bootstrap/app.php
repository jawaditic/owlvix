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
            'builder' => \App\Http\Middleware\AuthenticateBuilder::class,
        ]);
        // Smaller HTML pages: drop the indentation Blade leaves at the start of each line
        $middleware->web(append: [\App\Http\Middleware\TrimHtmlWhitespace::class, \App\Http\Middleware\BuilderHostOnly::class]);
        // The stats beacon is sent with navigator.sendBeacon and only records a page view.
        // Builder contact forms (f/*) are posted from static pages hosted anywhere.
        $middleware->validateCsrfTokens(except: ['t', 'f/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON errors (validation, not found, too many requests) for the mobile app's /api routes, and for any
        // request that asks for JSON, like the exit survey (this replaces Laravel's default, so it keeps that check)
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
