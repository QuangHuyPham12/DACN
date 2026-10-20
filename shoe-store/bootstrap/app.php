<?php

use App\Http\Middleware\CustomerCredentialVersion;
use App\Http\Middleware\CustomerSession;
use App\Http\Middleware\StoreRole;
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
        $middleware->web(append: [CustomerCredentialVersion::class]);
        $middleware->alias(['role' => StoreRole::class, 'customer' => CustomerSession::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
