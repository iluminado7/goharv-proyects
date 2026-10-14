<?php

namespace App\Models;

use App\Models\Concerns\ReceivedFromWeb;
use Illuminate\Database\Eloquent\Model;

/** Consulta del formulario "Quiero analizar mi empresa" (/consulting). */
class ConsultingRequest extends Model
{
    use ReceivedFromWeb;

    protected $fillable = [
        'challenge', 'goal', 'name', 'role', 'company', 'email', 'phone',
        'newsletter', 'page', 'locale', 'consented_at', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'newsletter'   => 'boolean',
            'consented_at' => 'datetime',
        ];
    }
}
