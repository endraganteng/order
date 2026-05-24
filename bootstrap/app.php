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
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\AdminAuthMiddleware::class,
            'waiter.auth' => \App\Http\Middleware\WaiterAuthMiddleware::class,
            'role.permission' => \App\Http\Middleware\CheckRolePermission::class,
        ]);

        // Apply role permission check to all admin routes after auth
        $middleware->appendToGroup('admin.auth', [
            \App\Http\Middleware\CheckRolePermission::class,
        ]);

        // Webhook endpoints menerima POST dari pihak luar tanpa CSRF token.
        // Pastikan endpoint webhook punya validasi sendiri (signature/secret).
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
