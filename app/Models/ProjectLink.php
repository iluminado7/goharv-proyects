<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProjectLink extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'label', 'url', 'position'];

    /** Largo maximo del nombre derivado, contando los puntos suspensivos. */
    public const LARGO_NOMBRE = 32;

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

    /**
     * Nombre para un enlace que se cargo sin nombre. Antes todos caian en el
     * literal "Enlace" y dos enlaces distintos quedaban con el mismo boton;
     * sacarlo de la URL los deja distinguibles sin obligar a escribir nada.
     */
    public static function labelFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($path !== '') {
            $ultimo = str_replace(['-', '_'], ' ', basename($path));

            return Str::limit(Str::title($ultimo), self::LARGO_NOMBRE - 1, '…');
        }

        $host = str_replace('www.', '', (string) parse_url($url, PHP_URL_HOST));

        return $host !== '' ? Str::limit($host, self::LARGO_NOMBRE - 1, '…') : 'Enlace';
    }
}
