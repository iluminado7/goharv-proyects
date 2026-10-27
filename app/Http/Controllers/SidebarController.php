<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Plegar la barra lateral es una preferencia, no algo de una sola pantalla:
 * va en una cookie y se aplica desde el servidor, igual que el tema. Con un
 * checkbox se perderia en cada navegacion, que es justo lo que no se quiere.
 */
class SidebarController extends Controller
{
    public const COOKIE = 'barra';

    private const DIAS = 525600;

    public function toggle(Request $request): RedirectResponse
    {
        $nuevo = static::current($request) === 'angosta' ? 'ancha' : 'angosta';

        Cookie::queue(static::COOKIE, $nuevo, self::DIAS);

        return back();
    }

    public static function current(Request $request): string
    {
        return $request->cookie(static::COOKIE) === 'angosta' ? 'angosta' : 'ancha';
    }
}
