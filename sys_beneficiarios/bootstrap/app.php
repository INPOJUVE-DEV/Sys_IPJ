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
    ->withMiddleware(function (Middleware $middleware) {
        // Alias Spatie Permission middlewares and API cross-cutting middleware
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'access.log' => \App\Http\Middleware\AccessLog::class,
            'etag' => \App\Http\Middleware\ETagMiddleware::class,
        ]);

        $middleware->append([
            \App\Http\Middleware\ProblemJsonMiddleware::class,
        ]);

        // Rate limit public API traffic
        $middleware->replaceInGroup('api', 'throttle:api', 'throttle:public');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
