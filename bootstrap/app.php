<?php

declare(strict_types=1);

use App\Http\Middleware\LogCallbacks;
use App\Http\Middleware\VerifyProviderSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('provider.callback', [
            'throttle:novaspins-callback',
            VerifyProviderSignature::class,
            LogCallbacks::class,
        ]);

        $middleware->appendToGroup('provider.callback.replay', [
            'throttle:novaspins-replay',
            VerifyProviderSignature::class,
            LogCallbacks::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
