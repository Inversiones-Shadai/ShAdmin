<?php

namespace Tests\Feature;

use App\Services\Images\Base64ImageSanitizer;
use Tests\TestCase;

class LivewirePayloadConfigurationTest extends TestCase
{
    public function test_payload_limit_supports_two_megabyte_images_without_disabling_guards(): void
    {
        $maxPayload = (int) config('livewire.payload.max_size');
        $largestImageAsBase64 = strlen(base64_encode(str_repeat('A', Base64ImageSanitizer::MAX_BYTES)));

        $this->assertSame(5 * 1024 * 1024, $maxPayload);
        $this->assertGreaterThan($largestImageAsBase64, $maxPayload);
        $this->assertSame(10, config('livewire.payload.max_nesting_depth'));
        $this->assertSame(50, config('livewire.payload.max_calls'));
        $this->assertSame(200, config('livewire.payload.max_components'));
    }
}
