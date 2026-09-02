<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // El is_active tambien se chequea en EnsureUserIsActive, que corre antes.
        // Se repite aca a proposito: es el acceso mas sensible del panel.
        abort_unless($user?->isAdmin() && $user->is_active, 403, 'Solo el responsable del panel puede entrar acá.');

        return $next($request);
    }
}
