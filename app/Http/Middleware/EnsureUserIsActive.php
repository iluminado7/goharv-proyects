<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dar de baja a alguien tiene que sacarlo del panel ahora, no cuando se le
 * ocurra cerrar sesion.
 *
 * El chequeo de is_active del login solo impide volver a entrar: con la sesion
 * abierta, una cuenta dada de baja seguia leyendo el tablero entero, y si era
 * responsable del panel podia entrar a Equipo y reactivarse sola. La Policy
 * frenaba las acciones sobre proyectos, pero las pantallas de lectura no pasan
 * por la Policy.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Esta cuenta está dada de baja. Hablá con el responsable del panel.',
            ]);
        }

        return $next($request);
    }
}
