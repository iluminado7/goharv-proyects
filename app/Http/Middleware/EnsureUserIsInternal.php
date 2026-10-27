<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Equipo es del equipo. El profesional externo entra a trabajar en los
 * proyectos que le delegaron y no tiene por que ver la lista de quienes
 * estamos adentro ni con que rol.
 */
class EnsureUserIsInternal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->isInternal() && $user->is_active, 403,
            'Esta sección es solo para el equipo.');

        return $next($request);
    }
}
