<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Una sola clase para los seis avisos: todos tienen la misma forma —alguien
 * hizo algo en un proyecto donde estás— y se distinguen por el tipo.
 *
 * La campana los recibe todos. Por correo salen solo los tres que dicen "esto
 * ahora es tuyo"; lo decide el propio enum en vaPorCorreo(), no esta clase,
 * así sumar un tipo nuevo obliga a contestar la pregunta en un solo lugar.
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
        // La campana nunca falla y va primero: si el correo se cae, el aviso
        // ya quedó guardado.
        return $this->tipo->vaPorCorreo()
            ? ['database', 'mail']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->tipo->asunto($this->project->name))
            ->view('mail.aviso', [
                'titulo'   => $this->tipo->label(),
                'frase'    => $this->tipo->frase(),
                'nombre'   => $notifiable->name,
                'proyecto' => $this->project->name,
                'actor'    => $this->actor?->name,
                'detalle'  => $this->detalle,
                'enlace'   => route('projects.show', $this->project),
                'accion'   => 'Ver el proyecto',
            ]);
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
