<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Consulta que llego desde el formulario de contacto de
 * cacciatoreabogados.com.ar (ver Api\CacciatoreMessageController).
 */
class CacciatoreMessage extends Model
{
    protected $fillable = [
        'name', 'last_name', 'company', 'email', 'phone', 'country',
        'message', 'page', 'locale', 'consented_at', 'ip',
    ];

    /** Idiomas del sitio (lo que llega en `locale`) => nombre para mostrar. */
    public const LOCALES = [
        'es' => 'Español',
        'en' => 'Inglés',
        'pt' => 'Portugués',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
        ];
    }

    /** El sitio pide nombre y apellido por separado: en el panel van juntos. */
    public function deQuien(): string
    {
        return trim($this->name.' '.$this->last_name) ?: ($this->email ?: 'Alguien del sitio');
    }

    /**
     * Llegadas entre dos dias, inclusive. Fechas Y-m-d; cualquiera puede faltar.
     *
     * Sin conversiones: el panel corre y guarda en hora argentina, asi que el
     * dia de created_at ya es el dia en que llego para quien lo mira.
     */
    public function scopeReceivedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q, $day) => $q->whereDate('created_at', '>=', $day))
            ->when($to, fn ($q, $day) => $q->whereDate('created_at', '<=', $day));
    }
}
