<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublishStorageLogos extends Command
{
    protected $signature = 'images:publish-logos';

    protected $description = 'Sube logos a Cellar y muestra los UUID para configurar el entorno';

    public function handle(): int
    {
        foreach (['navigation' => public_path('images/shadai-logo.png'), 'ticket' => resource_path('images/shlogo.jpg'), 'transfer' => resource_path('images/shadai-transfer-logo.png')] as $name => $file) {
            if (Str::isUuid(config('images.logos.'.$name) ?? '')) {
                $this->line('IMAGE_LOGO_'.strtoupper($name).'_UUID='.config('images.logos.'.$name));

                continue;
            }
            $uuid = (string) Str::uuid();
            $binary = file_get_contents($file);
            $mime = getimagesizefromstring($binary)['mime'];
            if (! Storage::disk(config('images.disk'))->put(config('images.prefix').'/'.$uuid, $binary, [
                'visibility' => 'private', 'ContentType' => $mime,
                'CacheControl' => 'private, max-age=31536000, immutable',
            ])) {
                $this->error('No se pudo subir el logo '.$name);

                return self::FAILURE;
            }
            $this->line('IMAGE_LOGO_'.strtoupper($name).'_UUID='.$uuid);
        }

        return self::SUCCESS;
    }
}
