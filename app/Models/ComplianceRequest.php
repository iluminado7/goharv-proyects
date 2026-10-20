<?php

namespace App\Models;

use App\Models\Concerns\ReceivedFromWeb;
use Illuminate\Database\Eloquent\Model;

/** Consulta del formulario "Quiero fortalecer mi empresa" (/compliance). */
class ComplianceRequest extends Model
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
