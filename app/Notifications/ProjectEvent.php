<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Una sola clase para los cuatro avisos: todos tienen la misma forma —alguien
 * hizo algo en un proyecto donde estás— y se distinguen por el tipo.
 *
 * Cuando haya correo configurado se le suma un toMail() y 'mail' al via(): los
 * lugares que disparan la notificación no hay que tocarlos.
 */
class ProjectEvent extends Notification
{
    public function __construct(
        public NotificationType $tipo,
        public Project $project,
        public ?User $actor = null,
        public ?string $detalle = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo'       => $this->tipo->value,
            'project_id' => $this->project->getKey(),
            // Congelados a propósito: si el proyecto se borra o alguien se
            // cambia el nombre, el aviso viejo tiene que seguir leyéndose.
            'proyecto'   => $this->project->name,
            'actor'      => $this->actor?->name,
            'detalle'    => $this->detalle,
        ];
    }
}
