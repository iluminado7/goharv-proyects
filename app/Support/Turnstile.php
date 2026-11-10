<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifica el token de Cloudflare Turnstile que manda un formulario del sitio.
 *
 * El widget del sitio solo emite el token: que sea valido lo decide Cloudflare,
 * y se le pregunta desde aca porque la clave secreta no puede estar en el
 * navegador. Cada token sirve una sola vez y vence a los cinco minutos.
 *
 * Falla cerrado: sin clave configurada, si Cloudflare no contesta o si
 * contesta raro, el token no vale. Es preferible que alguien tenga que
 * reintentar a que un error de configuracion deje pasar a todos los bots sin
 * que nadie se entere.
 */
class Turnstile
{
    private const URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function valido(?string $token, ?string $ip = null): bool
    {
        $secret = config('services.turnstile.secret');

        if (blank($secret)) {
            Log::warning('Turnstile sin configurar: falta TURNSTILE_SECRET_KEY. Se rechaza el envio.');

            return false;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $respuesta = Http::asForm()
                ->timeout(5)
                ->post(self::URL, array_filter([
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (\Throwable $e) {
            Log::warning('Turnstile no respondio.', ['error' => $e->getMessage()]);

            return false;
        }

        if (! $respuesta->successful()) {
            return false;
        }

        if ($respuesta->json('success') === true) {
            return true;
        }

        // Los codigos dicen si fue un token vencido, repetido o una clave mal
        // cargada. Lo ultimo es lo que hay que ver rapido, por eso va al log.
        Log::info('Turnstile rechazo un token.', ['codigos' => $respuesta->json('error-codes')]);

        return false;
    }
}
