<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quien entra con una clave que le mandaron por correo no hace nada mas hasta
 * cambiarla. Asi la clave que quedo escrita en esa casilla sirve una sola vez.
 *
 * Va en todo el grupo web y no en un puñado de rutas: una pantalla nueva no
 * tiene que acordarse de esto. Se dejan pasar las cuatro cosas que hacen falta
 * para poder cambiarla o irse.
 */
class EnsurePasswordIsChanged
{
    private const PERMITIDAS = [
        'password.change',
        'password.change.store',
        'logout',
        'theme.toggle',
        'sidebar.toggle',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs(self::PERMITIDAS)) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
