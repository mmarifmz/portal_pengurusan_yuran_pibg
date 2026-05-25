<?php

use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\AuthenticateTeacherApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Shetabit\Visitor\Middlewares\LogVisits;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            LogVisits::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserRole::class,
            'teacher.api.key' => AuthenticateTeacherApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
