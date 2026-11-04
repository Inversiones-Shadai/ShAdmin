<?php

namespace Tests\Feature;

use Tests\TestCase;

class SweetAlertLayeringTest extends TestCase
{
    public function test_sweetalert_uses_the_browser_top_layer_and_the_open_flux_dialog(): void
    {
        $script = file_get_contents(resource_path('js/alerts.js'));
        $styles = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($script);
        $this->assertIsString($styles);
        $this->assertStringContainsString(
            "document.querySelectorAll('[data-flux-modal] > dialog[open]')",
            $script,
        );
        $this->assertStringContainsString('topLayer: true', $script);
        $this->assertStringContainsString('await waitForInterface()', $script);
        $this->assertStringContainsString('z-index: 2147483647 !important', $styles);
    }
}
