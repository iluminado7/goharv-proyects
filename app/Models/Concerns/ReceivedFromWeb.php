<?php

namespace App\Models\Concerns;

use App\Support\WebForms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Lo que comparten las consultas y los pedidos que llegan del sitio. */
trait ReceivedFromWeb
{
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
