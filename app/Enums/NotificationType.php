<?php

namespace App\Enums;

/**
 * Lo que la campana avisa. La regla para sumar un caso nuevo: tiene que ser
 * algo que otro te hizo en un proyecto tuyo y sobre lo que podés hacer algo.
 * Si es solo "paso esto en el panel", va a la bitacora y no aca.
 */
enum NotificationType: string
{
    case TeAsignaron   = 'te_asignaron';
    case TeSumaron     = 'te_sumaron';
    case Comentario    = 'comentario';
    case CambioEstado  = 'cambio_estado';
    case TareaAsignada = 'tarea_asignada';
    case TareaHecha    = 'tarea_hecha';

    public function label(): string
    {
        return match ($this) {
            self::TeAsignaron  => 'Te asignaron un proyecto',
            self::TeSumaron    => 'Te sumaron a un proyecto',
            self::Comentario   => 'Nueva nota',
            self::CambioEstado => 'Cambio de estado',
            self::TareaAsignada => 'Te asignaron una tarea',
            self::TareaHecha    => 'Completaron una tarea',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TeAsignaron  => 'var(--warning)',
            self::TeSumaron    => 'var(--accent)',
            self::Comentario   => 'var(--success)',
            self::CambioEstado => 'var(--accent)',
            self::TareaAsignada => 'var(--warning)',
            self::TareaHecha    => 'var(--success)',
        };
    }
}
