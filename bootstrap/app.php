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
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // Detras de un proxy (ngrok, o un HTTPS por delante en produccion) Laravel
        // ve la request como http://localhost y arma los enlaces con ese host: el
        // CSS y los formularios apuntan a la maquina de quien mira, no al panel.
        // Con esto usa las cabeceras X-Forwarded-* y devuelve las URLs reales.
        // En produccion conviene cambiar '*' por la IP del proxy.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
