<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Регистрируем middleware для multi-tenancy
        $middleware->alias([
            'tenant.context' => \App\Http\Middleware\SetTenantContext::class,
            'tenant.status' => \App\Http\Middleware\CheckTenantStatus::class,
            'tenant.required' => \App\Http\Middleware\EnsureUserHasTenant::class,
            'super.admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'not.employee' => \App\Http\Middleware\EnsureNotEmployee::class,
            'admin.or.manager' => \App\Http\Middleware\CheckAdminOrManager::class,
        ]);

        // Inertia middleware
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\LogBroadcastingRequests::class, // Добавляем логирование broadcasting
        ]);

        // Добавляем поддержку сессий и cookies для API роутов
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
