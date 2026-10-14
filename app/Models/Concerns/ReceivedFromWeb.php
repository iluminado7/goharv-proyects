<?php

namespace App\Models\Concerns;

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Models\ConsultingRequest;
use App\Models\ContactMessage;
use App\Models\InCompanyRequest;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\InquiryReceived;
use App\Support\WebForms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/** Lo que comparten las consultas y los pedidos que llegan del sitio. */
trait ReceivedFromWeb
{
    /**
     * Avisar va en el trait y no en los cuatro controladores de la API: asi
     * ninguno se olvida, y un formulario nuevo queda cubierto por existir.
     */
    public static function bootReceivedFromWeb(): void
    {
        static::created(function (self $consulta) {
            $consulta->avisarQueLlego();
        });
    }

    /**
     * Anota en la bitácora y le avisa al equipo.
     *
     * Nada de esto puede voltear el envío: del otro lado hay alguien en el
     * sitio esperando un "¡Gracias!", y perder la consulta sería mucho peor
     * que perder el aviso.
     */
    protected function avisarQueLlego(): void
    {
        try {
            Activity::anotar(
                ActivityAction::ConsultaRecibida,
                subject: $this->deQuien(),
                detail: $this->formularioLabel(),
            );

            // A todo el equipo: no hay responsable asignado todavía y la
            // atiende quien pueda. Es el caso donde avisar tarde cuesta plata.
            Notification::send(
                User::where('is_active', true)->get(),
                new InquiryReceived(
                    $this->formulario(),
                    $this->formularioLabel(),
                    $this->deQuien(),
                    $this->company ?? null,
                )
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** El valor que espera `?form=` en la pantalla de Consultas web. */
    public function formulario(): string
    {
        return match (static::class) {
            ContactMessage::class    => WebForms::CONTACT,
            InCompanyRequest::class  => WebForms::IN_COMPANY,
            ConsultingRequest::class => WebForms::CONSULTING,
            // Los "avisame" se listan por programa, no todos juntos.
            Lead::class              => (string) $this->program,
        };
    }

    public function formularioLabel(): string
    {
        return match (static::class) {
            ContactMessage::class    => 'Contacto',
            InCompanyRequest::class  => 'Formación In Company',
            ConsultingRequest::class => 'Quiero analizar mi empresa',
            Lead::class              => 'Avisame: '.(WebForms::PROGRAMS[$this->program] ?? $this->program),
        };
    }

    public function deQuien(): string
    {
        return $this->name ?: ($this->email ?: 'Alguien del sitio');
    }

    /** Cuando llego, en hora de Argentina. */
    public function receivedAt(): Carbon
    {
        return $this->created_at->copy()->setTimezone(WebForms::TIMEZONE);
    }

    /**
     * Llegados entre dos dias (inclusive), contados en hora de Argentina.
     * Fechas en formato Y-m-d; cualquiera de las dos puede faltar.
     */
    public function scopeReceivedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q, $day) => $q->where(
                'created_at', '>=', Carbon::parse($day, WebForms::TIMEZONE)->startOfDay()->utc()
            ))
            ->when($to, fn ($q, $day) => $q->where(
                'created_at', '<=', Carbon::parse($day, WebForms::TIMEZONE)->endOfDay()->utc()
            ));
    }
}
