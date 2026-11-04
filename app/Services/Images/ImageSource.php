<?php

namespace App\Services\Images;

use InvalidArgumentException;

class ImageSource
{
    public function source(string $value): string
    {
        $url = parse_url($value);
        if (is_array($url) && isset($url['host'])) {
            $endpoint = parse_url((string) config('filesystems.disks.s3.endpoint'), PHP_URL_HOST);
            $bucket = (string) config('filesystems.disks.s3.bucket');
            if (($url['scheme'] ?? '') === 'https'
                && in_array($url['host'], [$endpoint, $bucket.'.'.$endpoint], true)) {
                return $value;
            }
            throw new InvalidArgumentException('La URL de la imagen no pertenece al storage configurado.');
        }
        $image = app(Base64ImageSanitizer::class)->validateBase64($value);

        return "data:{$image['mime']};base64,{$image['base64']}";
    }

    public function forRecord(?array $record, callable $legacy, string $field = 'imagen_url'): string
    {
        $url = $record[$field] ?? null;

        return $this->source(is_string($url) && $url !== '' ? $url : $legacy());
    }
}
