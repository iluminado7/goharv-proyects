<?php

namespace App\Enums;

enum Currency: string
{
    case Ars = 'ARS';
    case Usd = 'USD';

    public function label(): string
    {
        return match ($this) {
            self::Ars => 'Pesos',
            self::Usd => 'Dólares',
        };
    }

    public function simbolo(): string
    {
        return match ($this) {
            self::Ars => '$',
            self::Usd => 'US$',
        };
    }

    /**
     * Formato argentino: punto para los miles y coma para los decimales.
     *
     * Recibe string porque el cast decimal de Eloquent devuelve string y no
     * float: con plata, pasar por float es como se pierden los centavos.
     */
    public function formato(string|float|null $monto): ?string
    {
        if ($monto === null || $monto === '') {
            return null;
        }

        return $this->simbolo().' '.number_format((float) $monto, 2, ',', '.');
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
