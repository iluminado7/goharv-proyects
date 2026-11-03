<?php

namespace App\Enums;

/**
 * Como se cobra un proyecto.
 *
 * Es una lista cerrada y no texto libre para que mas adelante se pueda
 * calcular cuando entra la plata: "30 dias" es una cuenta, "a convenir con
 * Juan por mail" no.
 */
enum PaymentTerm: string
{
    case Contado     = 'contado';
    case Mitades     = 'mitades';
    case TreintaDias = '30_dias';
    case SesentaDias = '60_dias';
    case PorHito     = 'por_hito';

    public function label(): string
    {
        return match ($this) {
            self::Contado     => 'Contado',
            self::Mitades     => '50% y 50%',
            self::TreintaDias => '30 días',
            self::SesentaDias => '60 días',
            self::PorHito     => 'Por hito',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
