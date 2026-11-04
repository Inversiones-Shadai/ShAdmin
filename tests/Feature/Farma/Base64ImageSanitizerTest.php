<?php

namespace Tests\Feature\Farma;

use App\Services\Images\Base64ImageSanitizer;
use InvalidArgumentException;
use Tests\TestCase;

class Base64ImageSanitizerTest extends TestCase
{
    public function test_it_reencodes_png_and_removes_appended_content(): void
    {
        $image = imagecreatetruecolor(10, 10);
        imagefilledrectangle($image, 0, 0, 9, 9, imagecolorallocate($image, 20, 160, 90));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $this->assertIsString($png);
        $payload = $png.'<?php echo "unsafe";';

        $result = app(Base64ImageSanitizer::class)->sanitizeBase64(base64_encode($payload));
        $sanitized = base64_decode($result['base64'], true);

        $this->assertSame('image/png', $result['mime']);
        $this->assertIsString($sanitized);
        $this->assertStringNotContainsString('<?php', $sanitized);
        $this->assertLessThanOrEqual(Base64ImageSanitizer::MAX_BYTES, strlen($sanitized));
    }

    public function test_it_rejects_non_image_base64(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JPEG o PNG');

        app(Base64ImageSanitizer::class)->sanitizeBase64(base64_encode('not-an-image'));
    }

    public function test_it_validates_api_images_without_recompressing_them(): void
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image, null, 9, PNG_ALL_FILTERS);
        $png = ob_get_clean();
        imagedestroy($image);

        $this->assertIsString($png);
        $result = app(Base64ImageSanitizer::class)->validateBase64(base64_encode($png));

        $this->assertSame(base64_encode($png), $result['base64']);
        $this->assertSame('image/png', $result['mime']);
    }

    public function test_it_accepts_and_cleans_an_image_between_one_and_two_megabytes(): void
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $this->assertIsString($png);
        $result = app(Base64ImageSanitizer::class)->sanitizeBase64(
            base64_encode($png.str_repeat('A', 1_200_000)),
        );

        $this->assertSame('image/png', $result['mime']);
        $this->assertLessThanOrEqual(Base64ImageSanitizer::MAX_BYTES, strlen(base64_decode($result['base64'], true)));
    }
}
