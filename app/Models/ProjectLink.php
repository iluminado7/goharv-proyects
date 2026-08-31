<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectLink extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'label', 'url', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Dominio suelto, para mostrar de que es el enlace sin leer la URL entera. */
    public function host(): string
    {
        return str_replace('www.', '', parse_url($this->url, PHP_URL_HOST) ?: $this->url);
    }
}
