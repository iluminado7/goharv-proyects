<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras que el navegador necesita para defender al panel de cosas que el
 * servidor no puede evitar solo: que lo metan en un iframe ajeno, que adivine
 * tipos de archivo, que filtre la URL completa al salir a otro sitio.
 *
 * La CSP usa un nonce por request para los dos <script> del panel, en lugar de
 * habilitar 'unsafe-inline'. Si algun dia se cuela un script inyectado, no va a
 * tener el nonce y el navegador no lo ejecuta.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(24);

        View::share('cspNonce', $nonce);

        $response = $next($request);

        // Solo HTML: no tiene sentido en el CSS, los iconos o el manifest.
        if (! str_contains((string) $response->headers->get('content-type'), 'text/html')) {
            return $response;
        }

        $csp = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            // Las vistas usan atributos style= en varios lados, asi que el
            // inline de estilos no se puede cerrar sin reescribirlas.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            'font-src https://fonts.gstatic.com',
            "img-src 'self' data:",
            "connect-src 'self'",
            "manifest-src 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // HSTS solo si ya se esta sirviendo por HTTPS: mandarla sobre HTTP no
        // hace nada, y en local romperia el acceso por http://localhost.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
