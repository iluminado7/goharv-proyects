<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El correo que estrena una cuenta: usuario, clave provisoria y el link para
 * entrar.
 *
 * La clave viaja en texto plano y queda para siempre en esa casilla. Es lo
 * menos malo mientras no haya recuperacion por correo —la alternativa es
 * pasarsela por WhatsApp, que no mejora nada—, y por eso la cuenta nace con
 * must_change_password: apenas entra, el panel la obliga a cambiarla y la del
 * correo deja de servir. Cuando exista el link de un solo uso, este correo
 * tiene que dejar de llevar la clave.
 *
 * Solo por mail: la campana la ve quien ya esta adentro, y esta persona
 * todavia no entro nunca.
 */
class Bienvenida extends Notification
{
    public function __construct(
        public string $clave,
        public ?User $quien = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu cuenta en el panel de GoHarv')
            ->view('mail.bienvenida', [
                'nombre' => $notifiable->name,
                'correo' => $notifiable->email,
                'clave'  => $this->clave,
                'rol'    => $notifiable->role->label(),
                'quien'  => $this->quien?->name ?? 'Un responsable del panel',
                'enlace' => route('login'),
            ]);
    }
}
