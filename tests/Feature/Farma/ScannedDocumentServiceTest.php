<?php

namespace Tests\Feature\Farma;

use App\Services\Images\ScannedDocumentService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ScannedDocumentServiceTest extends TestCase
{
    public function test_it_normalizes_scanned_documents_to_safe_jpeg(): void
    {
        $scan = app(ScannedDocumentService::class)->scan(UploadedFile::fake()->image('factura.png', 1800, 2400));

        $this->assertSame('image/jpeg', $scan['mime']);
        $this->assertLessThanOrEqual(ScannedDocumentService::MAX_OUTPUT_BYTES, $scan['bytes']);
        $this->assertNotFalse(base64_decode($scan['base64'], true));
        $this->assertLessThanOrEqual(2400, max($scan['width'], $scan['height']));
    }
}
