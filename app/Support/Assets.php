<?php

namespace App\Support;

/**
 * Agrega al CSS un sufijo con su fecha de modificacion.
 *
 * Sin esto, cada cambio de estilo depende de que a alguien se le ocurra forzar
 * la recarga: entre el cache del navegador, el service worker de la PWA y el
 * CDN de Laravel Cloud, el archivo viejo sobrevive. Con la URL versionada el
 * archivo nuevo es otra direccion y ninguna capa lo puede confundir.
 */
class Assets
{
    /**
     * Se memoriza la fecha del archivo y no la URL entera: asset() depende del
     * host y el esquema de cada request, y guardarla haria que la primera
     * visita de un worker persistente le fije el http:// a todas las demas.
     *
     * @var array<string, int|null>
     */
    private static array $fechas = [];

    public static function versioned(string $ruta): string
    {
        $fecha = self::$fechas[$ruta] ??= self::fecha($ruta);

        return $fecha === null ? asset($ruta) : asset($ruta).'?v='.$fecha;
    }

    private static function fecha(string $ruta): ?int
    {
        $archivo = public_path($ruta);

        return is_file($archivo) ? filemtime($archivo) : null;
    }
}
