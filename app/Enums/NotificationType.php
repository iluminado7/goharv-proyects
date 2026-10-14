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

    public function label(): string
    {
        return match ($this) {
            self::TeAsignaron  => 'Te asignaron un proyecto',
            self::TeSumaron    => 'Te sumaron a un proyecto',
            self::Comentario   => 'Nueva nota',
            self::CambioEstado => 'Cambio de estado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TeAsignaron  => '#E0A33E',
            self::TeSumaron    => '#6BA5E7',
            self::Comentario   => '#4FA97C',
            self::CambioEstado => '#A585D8',
        };
    }
}
