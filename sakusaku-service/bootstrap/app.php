<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'resolve-tenant' => \App\Http\Middleware\ResolveTenant::class,
            'tenant-active' => \App\Http\Middleware\EnsureTenantActive::class,
            'admin-role' => \App\Http\Middleware\EnsureAdminRole::class,
            'user-level' => \App\Http\Middleware\EnsureUserLevel::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
