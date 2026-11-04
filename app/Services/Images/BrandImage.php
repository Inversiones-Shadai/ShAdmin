<?php

namespace App\Services\Images;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandImage
{
    public function source(string $name): string
    {
        $uuid = config('images.logos.'.$name);
        if (is_string($uuid) && Str::isUuid($uuid)) {
            return Storage::disk(config('images.disk'))->temporaryUrl(
                config('images.prefix').'/'.$uuid,
                now()->addMinutes(max(5, config('images.url_minutes'))),
            );
        }

        // Allows the application to boot before images:publish-logos is executed.
        return match ($name) {
            'navigation' => asset('images/shadai-logo.png'),
            'ticket' => 'data:image/jpeg;base64,'.base64_encode(file_get_contents(resource_path('images/shlogo.jpg'))),
            'transfer' => 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('images/shadai-transfer-logo.png'))),
        };
    }
}
