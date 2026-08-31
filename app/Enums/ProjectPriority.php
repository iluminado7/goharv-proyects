<?php

namespace App\Enums;

enum ProjectPriority: string
{
    case Alta  = 'alta';
    case Media = 'media';
    case Baja  = 'baja';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Menor peso = mas urgente. Se usa para ordenar. */
    public function weight(): int
    {
        return match ($this) {
            self::Alta  => 1,
            self::Media => 2,
            self::Baja  => 3,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Alta  => '#E05C4B',
            self::Media => '#C9A227',
            self::Baja  => '#5F6169',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
