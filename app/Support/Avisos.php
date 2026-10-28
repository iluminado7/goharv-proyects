<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notificaciones;

/**
 * Unico punto de salida de los avisos, con la misma regla que
 * Activity::anotar(): nunca puede voltear la accion que lo origina.
 *
 * Importa desde que los avisos tambien salen por correo. El correo sale en el
 * mismo request —no hay worker de colas— y depende de un servicio de afuera:
 * si Resend esta caido o tarda, lo que no puede pasar es que se pierda el
 * proyecto que alguien acaba de guardar. Se anota el problema en el log y la
 * campana ya guardo el aviso igual, que es el canal que no falla.
 */
class Avisos
{
    /**
     * Si el correo sale de verdad o se escribe en el log. Mientras no haya un
     * dominio verificado en Resend, el panel corre con MAIL_MAILER=log: todo
     * funciona igual, pero no le llega nada a nadie. Las pantallas que avisan
     * "le mandamos un mail" tienen que saberlo, o mienten.
     */
    public static function hayCorreo(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }

    /**
     * Si las notificaciones al celular estan configuradas. Sin las claves
     * VAPID el canal revienta al construirse, asi que se pregunta antes de
     * sumarlo: un panel recien clonado tiene que arrancar igual.
     */
    public static function hayPush(): bool
    {
        return filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    public static function mandar(Collection|array $destinatarios, Notification $aviso): void
    {
        $destinatarios = $destinatarios instanceof Collection
            ? $destinatarios
            : collect($destinatarios);

        if ($destinatarios->isEmpty()) {
            return;
        }

        try {
            Notificaciones::send($destinatarios, $aviso);
        } catch (\Throwable $e) {
            Log::error('No se pudo mandar un aviso.', [
                'aviso'         => $aviso::class,
                'destinatarios' => $destinatarios->pluck('id')->all(),
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
