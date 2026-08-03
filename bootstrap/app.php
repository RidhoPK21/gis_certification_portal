<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\PreventProxyTransform;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(
        function (Middleware $middleware): void {
            $middleware->web(prepend: [
                PreventProxyTransform::class,
            ]);

            $middleware->alias([
                'active' => EnsureActiveUser::class,
                'role' => RoleMiddleware::class,
                'permission' => PermissionMiddleware::class,
            ]);
        }
    )
    ->withExceptions(
        function (Exceptions $exceptions): void {
            //
        }
    )
    ->create();