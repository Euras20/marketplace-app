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
        'admin.store' => \App\Http\Middleware\AdminStoreContext::class,
    ]);
})

->withSingletons([
    \App\Services\StoreContext::class,
])
->withProviders([
    \App\Providers\AppServiceProvider::class,
])
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'resolve.public.store' => \App\Http\Middleware\ResolvePublicStore::class,
        'admin.store'          => \App\Http\Middleware\AdminStoreContext::class,
        'require.age'          => \App\Http\Middleware\RequireAgeAssurance::class,
        'rate.sensitive'       => \App\Http\Middleware\RateLimitSensitiveEndpoints::class,
    ]);
})
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();