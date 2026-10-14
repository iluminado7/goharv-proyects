<?php

namespace App\Models;

use App\Models\Concerns\ReceivedFromWeb;
use Illuminate\Database\Eloquent\Model;

/** Consulta del formulario de Formacion In Company (/academy/formacion-in-company). */
class InCompanyRequest extends Model
{
    use ReceivedFromWeb;

    protected $fillable = [
        'name', 'role', 'company', 'email', 'phone', 'team_size', 'areas',
        'modality', 'message', 'newsletter', 'page', 'locale', 'consented_at', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'areas'        => 'array',
            'newsletter'   => 'boolean',
            'consented_at' => 'datetime',
        ];
    }
}
