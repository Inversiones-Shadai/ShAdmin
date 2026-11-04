<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalImageViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_contains_the_persistent_global_image_viewer(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="global-image-viewer"', false)
            ->assertSee('data-image-viewer-cache-status', false);
    }

    public function test_image_viewer_uses_an_in_memory_lru_cache(): void
    {
        $source = file_get_contents(resource_path('js/image-viewer.js'));

        $this->assertIsString($source);
        $this->assertStringContainsString('window.ShAdminImageCache', $source);
        $this->assertStringContainsString('MAX_CACHE_ENTRIES = 24', $source);
        $this->assertStringContainsString("document.addEventListener('image-viewer:invalidate'", $source);
        $this->assertStringNotContainsString('localStorage', $source);
        $this->assertStringNotContainsString('sessionStorage', $source);
    }
}
