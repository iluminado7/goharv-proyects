<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin    = 'admin';
    case Member   = 'member';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Admin    => 'Responsable',
            self::Member   => 'Socio',
            self::External => 'Profesional externo',
        };
    }

    /**
     * Quien es del equipo. El profesional externo no lo es: entra a trabajar
     * en los proyectos que le delegan y no ve nada mas del panel.
     */
    public function isInternal(): bool
    {
        return $this !== self::External;
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
