<?php

namespace App\Models;

use App\Models\Concerns\ReceivedFromWeb;
use Illuminate\Database\Eloquent\Model;

/** Pedido de "Avisame" de un programa en desarrollo (paginas de unidad del sitio). */
class Lead extends Model
{
    use ReceivedFromWeb;

    protected $fillable = [
        'name', 'email', 'phone', 'newsletter', 'program', 'source',
        'page', 'consented_at', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'newsletter'   => 'boolean',
            'consented_at' => 'datetime',
        ];
    }
}
