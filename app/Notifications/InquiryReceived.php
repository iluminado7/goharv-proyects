<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\Notification;

/**
 * Llegó una consulta por el sitio institucional.
 *
 * No usa ProjectEvent porque detrás no hay ningún proyecto: la manda alguien
 * de afuera y lo que importa es qué formulario completó y quién fue.
 */
class InquiryReceived extends Notification
{
    public function __construct(
        public string $formulario,
        public string $formularioLabel,
        public string $dequien,
        public ?string $empresa = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo'     => NotificationType::ConsultaWeb->value,
            'proyecto' => $this->formularioLabel,
            'actor'    => $this->dequien,
            'detalle'  => $this->empresa,
            // Las consultas no tienen ficha propia: el aviso lleva al listado
            // ya filtrado por el formulario que completaron.
            'url'      => route('inquiries.index', ['form' => $this->formulario]),
        ];
    }
}
