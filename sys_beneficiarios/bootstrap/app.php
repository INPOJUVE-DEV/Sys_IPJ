<?php

use App\Http\Middleware\AccessLog;
use App\Http\Middleware\ETagMiddleware;
use App\Http\Middleware\ProblemJsonMiddleware;
use App\Http\Middleware\SetVolAuditFields;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('api', ProblemJsonMiddleware::class);

        $middleware->alias([
            'access.log' => AccessLog::class,
            'etag' => ETagMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'vol.audit' => SetVolAuditFields::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withProviders(require __DIR__.'/providers.php')
    ->create();
