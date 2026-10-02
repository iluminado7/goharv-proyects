<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Recorta la foto a un cuadrado y la achica, con GD, que ya viene con PHP.
 *
 * Se re-codifica siempre a JPEG en lugar de guardar el archivo original: queda
 * en unos 20 KB en vez de varios MB, y de paso se descarta cualquier cosa rara
 * que el archivo traiga adentro (metadatos, payloads escondidos en un PNG).
 */
class AvatarImage
{
    /** Lado del cuadrado final. Alcanza para una pantalla retina. */
    public const LADO = 256;

    public const MIME = 'image/jpeg';

    public static function fromUpload(UploadedFile $archivo): string
    {
        $original = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));

        if ($original === false) {
            throw new RuntimeException('No se pudo leer la imagen.');
        }

        $ancho = imagesx($original);
        $alto  = imagesy($original);
        $lado  = min($ancho, $alto);

        // Recorte centrado: lo que se ve es el medio de la foto.
        $x = (int) (($ancho - $lado) / 2);
        $y = (int) (($alto - $lado) / 2);

        $destino = imagecreatetruecolor(self::LADO, self::LADO);

        // Fondo blanco: los PNG con transparencia quedarian negros en JPEG.
        imagefilledrectangle($destino, 0, 0, self::LADO, self::LADO, imagecolorallocate($destino, 255, 255, 255));
        imagecopyresampled($destino, $original, 0, 0, $x, $y, self::LADO, self::LADO, $lado, $lado);

        ob_start();
        imagejpeg($destino, null, 82);
        $binario = (string) ob_get_clean();

        imagedestroy($original);
        imagedestroy($destino);

        return $binario;
    }
}
