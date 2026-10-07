<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ProductoImagen;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class ImagenProductoService
{
    /** Lado mayor máximo en píxeles: suficiente para miniaturas y fichas, y liviano para la base de datos. */
    public const LADO_MAXIMO = 480;

    /**
     * Reduce la foto y la guarda (una por producto; reemplaza la anterior).
     */
    public function guardar(Producto $producto, UploadedFile $archivo): ProductoImagen
    {
        [$mime, $binario] = $this->reducir($archivo->getRealPath());

        return ProductoImagen::updateOrCreate(
            ['producto_id' => $producto->id],
            ['mime' => $mime, 'contenido' => base64_encode($binario)],
        );
    }

    /**
     * @return array{0: string, 1: string} [mime, contenido binario]
     */
    private function reducir(string $ruta): array
    {
        $bytes = file_get_contents($ruta);
        $origen = $bytes === false ? false : @imagecreatefromstring($bytes);

        if ($origen === false) {
            throw new InvalidArgumentException('La imagen no se pudo leer.');
        }

        $origen = $this->corregirOrientacion($origen, $ruta);

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $escala = min(1, self::LADO_MAXIMO / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefill($destino, 0, 0, imagecolorallocatealpha($destino, 255, 255, 255, 127));
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($destino, null, 78);
            $mime = 'image/webp';
        } else {
            imagepng($destino, null, 6);
            $mime = 'image/png';
        }
        $binario = (string) ob_get_clean();

        return [$mime, $binario];
    }

    /**
     * Las fotos tomadas con el celular suelen venir "acostadas" con una marca
     * EXIF de orientación; se gira la imagen para que se vea derecha.
     */
    private function corregirOrientacion(\GdImage $imagen, string $ruta): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($ruta);
        $angulo = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angulo === 0) {
            return $imagen;
        }

        return imagerotate($imagen, $angulo, 0) ?: $imagen;
    }
}
