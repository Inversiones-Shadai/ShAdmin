<?php

namespace Tests\Feature;

use App\Services\Images\BrandImage;
use App\Services\Images\ImageSource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorageImageSourceTest extends TestCase
{
    public function test_storage_url_is_used_without_calling_the_api(): void
    {
        config(['filesystems.disks.s3.endpoint' => 'https://cellar-c2.services.clever-cloud.com',
            'filesystems.disks.s3.bucket' => 'shadaibucket']);
        $url = 'https://shadaibucket.cellar-c2.services.clever-cloud.com/shadmin/images/uuid?X-Amz-Signature=test';
        $source = app(ImageSource::class)->forRecord(['imagen_url' => $url], function (): never {
            $this->fail('El listado ya contiene la URL; no debe llamar a la API de imágenes.');
        });
        $this->assertSame($url, $source);
    }

    public function test_unrelated_remote_urls_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(ImageSource::class)->source('https://untrusted.example/image.png');
    }

    public function test_invoice_and_payment_urls_are_used_directly_from_the_invoices_directory(): void
    {
        config(['filesystems.disks.s3.endpoint' => 'https://cellar-c2.services.clever-cloud.com',
            'filesystems.disks.s3.bucket' => 'shadaibucket']);
        $url = 'https://shadaibucket.cellar-c2.services.clever-cloud.com/invoices/'.Str::uuid().'?X-Amz-Signature=test';
        $source = app(ImageSource::class)->forRecord(['documento_url' => $url], function (): never {
            $this->fail('El documento debe descargarse directamente del storage.');
        }, 'documento_url');

        $this->assertSame($url, $source);
    }

    public function test_configured_logos_use_direct_signed_storage_urls(): void
    {
        $uuid = (string) Str::uuid();
        config(['images.disk' => 'logo-test', 'images.prefix' => 'shadmin/images',
            'images.logos.navigation' => $uuid, 'images.logos.ticket' => $uuid, 'images.logos.transfer' => $uuid]);
        Storage::fake('logo-test')->buildTemporaryUrlsUsing(
            fn ($path, $expiration) => 'https://storage.test/'.$path.'?expires='.$expiration->timestamp,
        );
        foreach (['navigation', 'ticket', 'transfer'] as $name) {
            $url = app(BrandImage::class)->source($name);
            $this->assertStringStartsWith('https://storage.test/shadmin/images/'.$uuid, $url);
            $this->assertStringNotContainsString('base64', $url);
        }
    }
}
