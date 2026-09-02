<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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

        // En todo el grupo web y no solo en las rutas con 'auth': asi ninguna
        // pantalla nueva se olvida de expulsar a las cuentas dadas de baja.
        // Para las visitas sin sesion no hace nada.
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureUserIsActive::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);

        // Detras de un proxy (Laravel Cloud, o un HTTPS por delante) Laravel ve
        // la request como http:// y arma los enlaces mal: el CSS y los
        // formularios terminan apuntando a la maquina de quien mira.
        //
        // Se confia en el esquema, el puerto y la IP de origen, pero NO en
        // X-Forwarded-Host: esa cabecera la puede mandar cualquiera y sirve
        // para que el panel genere URLs hacia un dominio ajeno. El Host real
        // alcanza. Cuando haya recuperacion de clave por correo, ese enlace se
        // arma con el host: si se confiara en la cabecera, se podria envenenar.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
