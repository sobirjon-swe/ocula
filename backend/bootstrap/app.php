<?php

use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\SetLocaleFromRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Admin SPA cookie orqali (statefulApi). Mijoz kabineti (Mini App)
        // Bearer token bilan — u Telegram webview ichida, boshqa
        // origin'dan ishlaydi (BOSQICH-9.md §3, §5 #3).
        $middleware->statefulApi();

        $middleware->api(append: [
            SetLocaleFromRequest::class,
        ]);

        $middleware->alias([
            'idempotency' => EnsureIdempotency::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Backend faqat API beradi (PROJECT.md §2) — javob har doim JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
