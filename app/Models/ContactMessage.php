<?php

namespace App\Models;

use App\Models\Concerns\ReceivedFromWeb;
use Illuminate\Database\Eloquent\Model;

/** Consulta que llego desde el formulario de contacto del sitio. */
class ContactMessage extends Model
{
    use ReceivedFromWeb;

    protected $fillable = [
        'name', 'email', 'phone', 'company', 'country', 'unit',
        'message', 'page', 'consented_at', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
        ];
    }
}
