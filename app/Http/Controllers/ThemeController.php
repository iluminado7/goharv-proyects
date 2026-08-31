<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * El fondo se guarda en una cookie y se aplica en el <html> desde el servidor.
 * Sale mas barato que sumar JS al panel y no hay parpadeo al cargar la pagina.
 */
class ThemeController extends Controller
{
    public const COOKIE = 'tema';

    /** Un ano: es una preferencia, no algo que convenga volver a preguntar. */
    private const DIAS = 525600;

    public function toggle(Request $request): RedirectResponse
    {
        $nuevo = static::current($request) === 'claro' ? 'oscuro' : 'claro';

        Cookie::queue(static::COOKIE, $nuevo, self::DIAS);

        return back();
    }

    public static function current(Request $request): string
    {
        return $request->cookie(static::COOKIE) === 'claro' ? 'claro' : 'oscuro';
    }
}
