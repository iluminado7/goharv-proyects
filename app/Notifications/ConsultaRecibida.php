<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\CacciatoreMessage;
use App\Support\Avisos;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Llego una consulta por cacciatoreabogados.com.ar.
 *
 * No usa ProjectEvent porque detras no hay ningun proyecto: la manda alguien
 * de afuera y lo que importa es quien fue y de que empresa.
 *
 * Campana y celular, sin correo: el mail con la consulta completa ya le llega
 * al estudio (CacciatoreMessageReceived). Uno mas por persona del equipo
 * seria el mismo aviso repetido.
 */
class ConsultaRecibida extends Notification
{
    public function __construct(public CacciatoreMessage $consulta) {}

    public function via(object $notifiable): array
    {
        $canales = ['database'];

        // Clase y no 'webpush': ver la explicacion en ProjectEvent::via().
        if (Avisos::hayPush()) {
            $canales[] = WebPushChannel::class;
        }

        return $canales;
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(NotificationType::ConsultaWeb->label())
            ->body($this->consulta->deQuien().' · '.$this->consulta->company)
            ->icon(asset('icons/icon-192.png'))
            ->badge(asset('icons/badge-96.png'))
            ->tag('consulta-'.$this->consulta->getKey())
            ->data(['url' => route('inquiries.index')]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo'     => NotificationType::ConsultaWeb->value,
            // Congelados, como en ProjectEvent: el aviso se sigue leyendo
            // aunque la consulta se borre.
            'proyecto' => $this->consulta->deQuien(),
            'actor'    => null,
            'detalle'  => $this->consulta->company,
        ];
    }
}
