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

    /**
     * Cuales ademas del aviso en la campana mandan un correo.
     *
     * Solo los tres que dicen "esto ahora es tuyo". Los otros tres —una nota,
     * un cambio de estado, una tarea que otro completo— son para enterarse, y
     * en un proyecto activo son varios por dia: por correo se vuelven ruido y
     * la gente termina filtrando todo, incluso los que importan.
     */
    public function vaPorCorreo(): bool
    {
        return in_array($this, [self::TeAsignaron, self::TeSumaron, self::TareaAsignada], true);
    }

    /** La linea del asunto. Mas corta y directa que el label de la campana. */
    public function asunto(string $proyecto): string
    {
        return match ($this) {
            self::TeAsignaron   => 'Quedaste a cargo de «'.$proyecto.'»',
            self::TeSumaron     => 'Te sumaron a «'.$proyecto.'»',
            self::TareaAsignada => 'Nueva tarea en «'.$proyecto.'»',
            default             => $this->label(),
        };
    }

    /** Lo que hizo el otro, para armar la frase del correo. */
    public function frase(): string
    {
        return match ($this) {
            self::TeAsignaron   => 'te puso a cargo de',
            self::TeSumaron     => 'te sumó como colaborador a',
            self::TareaAsignada => 'te asignó una tarea en',
            self::Comentario    => 'dejó una nota en',
            self::CambioEstado  => 'movió de estado',
            self::TareaHecha    => 'completó una tarea en',
        };
    }

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
