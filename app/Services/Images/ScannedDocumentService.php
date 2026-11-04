<?php

namespace App\Services\Images;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

use function in_array;
use function is_string;
use function strlen;

class ScannedDocumentService
{
    public const int MAX_OUTPUT_BYTES = 1_048_576;

    private const int MAX_INPUT_BYTES = 12_582_912;

    private const int MAX_PIXELS = 30_000_000;

    private const int MAX_EDGE = 2400;

    /** @return array{base64: string, mime: string, width: int, height: int, bytes: int} */
    public function scan(UploadedFile $file): array
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_INPUT_BYTES) {
            throw new InvalidArgumentException('El documento no es válido o excede 12 MB.');
        }

        $binary = file_get_contents($file->getRealPath());

        if (! is_string($binary) || $binary === '') {
            throw new InvalidArgumentException('No fue posible leer el documento seleccionado.');
        }

        $info = getimagesizefromstring($binary);

        if ($info === false || ! in_array($info['mime'], ['image/jpeg', 'image/png'], true)) {
            throw new InvalidArgumentException('El documento debe ser una imagen JPEG o PNG.');
        }

        $width = (int) $info[0];
        $height = (int) $info[1];

        if ($width < 1 || $height < 1 || ($width * $height) > self::MAX_PIXELS) {
            throw new InvalidArgumentException('Las dimensiones del documento no son válidas.');
        }

        $source = imagecreatefromstring($binary);

        if ($source === false) {
            throw new InvalidArgumentException('El documento está dañado o no es válido.');
        }

        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($target === false) {
            unset($source);
            throw new InvalidArgumentException('No fue posible procesar el documento.');
        }

        $white = imagecolorallocate($target, 255, 255, 255);

        if ($white === false) {
            unset($source, $target);

            throw new InvalidArgumentException('No fue posible preparar el documento.');
        }

        imagefill($target, 0, 0, $white);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        unset($source);

        try {
            $encoded = '';

            foreach ([88, 82, 76, 70, 64, 58] as $quality) {
                ob_start();
                imageinterlace($target, true);
                imagejpeg($target, null, $quality);
                $encoded = (string) ob_get_clean();

                if ($encoded !== '' && strlen($encoded) <= self::MAX_OUTPUT_BYTES) {
                    break;
                }
            }
        } finally {
            unset($target);
        }

        if ($encoded === '' || strlen($encoded) > self::MAX_OUTPUT_BYTES) {
            throw new InvalidArgumentException('No fue posible reducir el documento a 1 MB sin perder legibilidad.');
        }

        return [
            'base64' => base64_encode($encoded),
            'mime' => 'image/jpeg',
            'width' => $targetWidth,
            'height' => $targetHeight,
            'bytes' => strlen($encoded),
        ];
    }
}
