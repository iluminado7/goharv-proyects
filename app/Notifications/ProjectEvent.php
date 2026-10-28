<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Project;
use App\Models\User;
use App\Support\Avisos;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

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
        // La campana nunca falla y va primero: si el correo o el push se caen,
        // el aviso ya quedó guardado.
        $canales = ['database'];

        if (! $this->tipo->vaPorCorreo()) {
            return $canales;
        }

        $canales[] = 'mail';

        // Los tres canales llevan los mismos avisos: lo que merece sacarte del
        // panel merece sacarte del panel, por donde sea que estés mirando.
        //
        // Va la clase y no el string 'webpush': el paquete no registra ese
        // alias, solo prepara las dependencias de la clase. Laravel resuelve
        // un canal por nombre de clase cuando no conoce el nombre corto, y
        // con el alias inventado tiraba «Driver not supported» —que Avisos se
        // tragaba, asi que no llegaba nada y no se veia ningun error.
        if (Avisos::hayPush()) {
            $canales[] = WebPushChannel::class;
        }

        return $canales;
    }

    /**
     * El aviso que aparece en la pantalla del celular. Corto a propósito: un
     * título que se lee de un vistazo y el nombre del proyecto.
     */
    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->tipo->label())
            ->body($this->project->name.($this->detalle ? ' · '.$this->detalle : ''))
            ->icon(asset('icons/icon-192.png'))
            ->badge(asset('icons/icon-192.png'))
            // Un aviso por proyecto: si llegan tres seguidos del mismo, se
            // pisan en vez de apilarse.
            ->tag('proyecto-'.$this->project->getKey())
            ->data(['url' => route('projects.show', $this->project)]);
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
