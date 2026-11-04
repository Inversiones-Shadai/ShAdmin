<?php

namespace App\Services\Images;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

use function is_string;
use function strlen;

class Base64ImageSanitizer
{
    public const int MAX_BYTES = 2_097_152;

    private const int MAX_PIXELS = 10_000_000;

    private const int JPEG_QUALITY = 92;

    /**
     * @return array{base64: string, mime: string, width: int, height: int}
     */
    public function sanitizeUploadedFile(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('No fue posible leer la imagen seleccionada.');
        }

        $contents = file_get_contents($file->getRealPath());

        if (! is_string($contents)) {
            throw new InvalidArgumentException('No fue posible leer la imagen seleccionada.');
        }

        return $this->sanitizeBinary($contents);
    }

    /**
     * @return array{base64: string, mime: string, width: int, height: int}
     */
    public function sanitizeBase64(string $value): array
    {
        return $this->sanitizeBinary($this->decodeBase64($value));
    }

    /**
     * Valida una imagen recibida de la API sin volver a comprimirla.
     *
     * @return array{base64: string, mime: string, width: int, height: int}
     */
    public function validateBase64(string $value): array
    {
        $binary = $this->decodeBase64($value);
        $details = $this->inspect($binary);

        if (! $this->hasValidImageStructure($binary)) {
            throw new InvalidArgumentException('La imagen contiene datos adicionales no permitidos.');
        }

        return [
            'base64' => base64_encode($binary),
            'mime' => $details['mime'],
            'width' => $details['width'],
            'height' => $details['height'],
        ];
    }

    /**
     * @return array{base64: string, mime: string, width: int, height: int}
     */
    private function sanitizeBinary(string $binary): array
    {
        $details = $this->inspect($binary);

        $image = imagecreatefromstring($binary);

        if ($image === false) {
            throw new InvalidArgumentException('La imagen está dañada o no es válida.');
        }

        $sanitized = $this->encodePng($image);
        $mime = 'image/png';

        if (strlen($sanitized) > self::MAX_BYTES && $details['mime'] === 'image/jpeg') {
            $sanitized = $this->encodeJpeg($image);
            $mime = 'image/jpeg';
        }

        unset($image);

        if ($sanitized === '' || strlen($sanitized) > self::MAX_BYTES) {
            throw new InvalidArgumentException('La imagen saneada no puede exceder 2 MB.');
        }

        return [
            'base64' => base64_encode($sanitized),
            'mime' => $mime,
            'width' => $details['width'],
            'height' => $details['height'],
        ];
    }

    private function decodeBase64(string $value): string
    {
        $value = trim($value);

        if (str_starts_with($value, 'data:')) {
            if (! preg_match('/^data:image\/(?:jpeg|png);base64,(.+)$/s', $value, $matches)) {
                throw new InvalidArgumentException('La imagen debe estar en formato JPEG o PNG.');
            }

            $value = $matches[1];
        }

        $binary = base64_decode($value, true);

        if (! is_string($binary)) {
            throw new InvalidArgumentException('La imagen no contiene un base64 válido.');
        }

        return $binary;
    }

    /** @return array{mime: string, width: int, height: int} */
    private function inspect(string $binary): array
    {
        if ($binary === '' || strlen($binary) > self::MAX_BYTES) {
            throw new InvalidArgumentException('La imagen no puede exceder 2 MB.');
        }

        $details = getimagesizefromstring($binary);

        if ($details === false || ! in_array($details['mime'], ['image/jpeg', 'image/png'], true)) {
            throw new InvalidArgumentException('La imagen debe estar en formato JPEG o PNG.');
        }

        $width = $details[0];
        $height = $details[1];

        if ($width < 1 || $height < 1 || ($width * $height) > self::MAX_PIXELS) {
            throw new InvalidArgumentException('Las dimensiones de la imagen no son válidas.');
        }

        return ['mime' => $details['mime'], 'width' => $width, 'height' => $height];
    }

    private function encodePng(\GdImage $image): string
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 9, PNG_ALL_FILTERS);

        return (string) ob_get_clean();
    }

    private function encodeJpeg(\GdImage $image): string
    {
        imageinterlace($image, true);
        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);

        return (string) ob_get_clean();
    }

    private function hasValidImageStructure(string $binary): bool
    {
        if (str_starts_with($binary, "\x89PNG\r\n\x1A\n")) {
            return str_ends_with($binary, "\x00\x00\x00\x00IEND\xAE\x42\x60\x82");
        }

        return str_starts_with($binary, "\xFF\xD8")
            && str_ends_with($binary, "\xFF\xD9");
    }
}
