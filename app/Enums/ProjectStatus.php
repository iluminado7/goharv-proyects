<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Nuevo        = 'nuevo';
    case Inicio       = 'inicio';
    case EnDesarrollo = 'en_desarrollo';
    case Terminado    = 'terminado';

    public function label(): string
    {
        return match ($this) {
            self::Nuevo        => 'Nuevo',
            self::Inicio       => 'Inicio',
            self::EnDesarrollo => 'En desarrollo',
            self::Terminado    => 'Terminado',
        };
    }

    /** Posicion dentro de la secuencia de avance (1 a 4). */
    public function step(): int
    {
        return match ($this) {
            self::Nuevo        => 1,
            self::Inicio       => 2,
            self::EnDesarrollo => 3,
            self::Terminado    => 4,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Nuevo        => '#7E838C',
            self::Inicio       => '#6BA5E7',
            self::EnDesarrollo => '#E0A33E',
            self::Terminado    => '#4FA97C',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
