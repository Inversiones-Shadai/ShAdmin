<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HtmlResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_html_uses_a_shared_logo_without_forcing_transport_headers(): void
    {
        foreach ([[], ['X-Livewire-Navigate' => '1']] as $headers) {
            $response = $this->get(route('login'), $headers)->assertOk();

            $response->assertSee('/images/shadai-logo.png', false)
                ->assertDontSee('data:image/png;base64,', false)
                ->assertSee('</html>', false)
                ->assertHeaderMissing('Content-Length')
                ->assertHeaderMissing('Transfer-Encoding');
        }

        $this->assertFileEquals(resource_path('images/shadai-transfer-logo.png'), public_path('images/shadai-logo.png'));
    }

    public function test_authenticated_page_includes_livewire_assets_without_forcing_transport_headers(): void
    {
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('dashboard'), ['X-Livewire-Navigate' => '1'])->assertOk();

        $response->assertSee('data-update-uri', false)
            ->assertSee('</html>', false)
            ->assertHeaderMissing('Content-Length')
            ->assertHeaderMissing('Transfer-Encoding');
    }

    public function test_local_development_login_html_has_an_exact_byte_length(): void
    {
        $this->app->instance('env', 'local');
        $this->withServerVariables(['SERVER_SOFTWARE' => 'PHP/8.5.12 (Development Server)']);
        foreach ([[], ['X-Livewire-Navigate' => '1']] as $headers) {
            $response = $this->get(route('login'), $headers)->assertOk();
            $content = $response->getContent();
            $response->assertHeader('Content-Length', (string) strlen($content))
                ->assertHeaderMissing('Transfer-Encoding')->assertSee('</html>', false);
        }
    }

    public function test_local_development_length_includes_the_late_livewire_assets(): void
    {
        $this->app->instance('env', 'local');
        $this->withServerVariables(['SERVER_SOFTWARE' => 'PHP/8.5.12 (Development Server)']);
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('dashboard'), ['X-Livewire-Navigate' => '1'])->assertOk();
        $content = $response->getContent();

        $this->assertTrue(str_contains($content, 'data-update-uri'), 'The final HTML must include the injected Livewire script.');
        $response->assertHeader('Content-Length', (string) strlen($content));
    }

    public function test_html_length_is_not_forced_for_production_or_other_web_servers(): void
    {
        $this->app->instance('env', 'production');
        $this->withServerVariables(['SERVER_SOFTWARE' => 'PHP/8.5.12 (Development Server)']);
        $this->get(route('login'))->assertOk()->assertHeaderMissing('Content-Length');

        $this->app->instance('env', 'local');
        $this->withServerVariables(['SERVER_SOFTWARE' => 'Apache/2.4']);
        $this->get(route('login'))->assertOk()->assertHeaderMissing('Content-Length');
    }

    public function test_local_server_preserves_streamed_encoded_and_non_html_response_framing(): void
    {
        $this->app->instance('env', 'local');
        $this->withServerVariables(['SERVER_SOFTWARE' => 'PHP/8.5.12 (Development Server)']);
        Route::get('/transport-stream', fn () => response()->stream(fn () => print '<html>stream</html>', 200, ['Content-Type' => 'text/html']));
        Route::get('/transport-encoded', fn () => response('<html>encoded</html>', 200, ['Content-Type' => 'text/html', 'Content-Encoding' => 'gzip']));
        Route::get('/transport-chunked', fn () => response('<html>chunked</html>', 200, ['Content-Type' => 'text/html', 'Transfer-Encoding' => 'chunked']));
        Route::get('/transport-json', fn () => response()->json(['ok' => true]));
        Route::get('/transport-error', fn () => response('<html>error</html>', 500, ['Content-Type' => 'text/html']));

        foreach (['stream', 'encoded', 'chunked', 'json', 'error'] as $kind) {
            $this->get('/transport-'.$kind)->assertHeaderMissing('Content-Length');
        }
    }
}
