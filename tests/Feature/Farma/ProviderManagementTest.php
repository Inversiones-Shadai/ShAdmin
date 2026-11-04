<?php

namespace Tests\Feature\Farma;

use App\Livewire\Farma\Proveedores;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProviderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.provider_path' => '/farma/proveedores',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', $this->allProviderPermissions());
        Http::preventStrayRequests();
    }

    public function test_component_lists_providers_and_exposes_route_in_farma_menu(): void
    {
        $this->fakeProviders();

        Livewire::test(Proveedores::class)
            ->assertSee('Farmacéutica Uno')
            ->assertSee('2233-4455')
            ->assertSee('data-test="view-provider-image-button"', false)
            ->assertSet('total', 31);

        $this->get(route('farma.proveedores'))
            ->assertOk()
            ->assertSee('Proveedores');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="permission-view-frm-prv"', false)
            ->assertSee('data:image/png;base64,', false);
    }

    public function test_component_creates_updates_and_deletes_with_a_sanitized_image(): void
    {
        $this->fakeProviders();
        $image = $this->pngBase64();

        Http::fake([
            'api.example.test/api/farma/proveedores/crear' => Http::response([
                'message' => 'Proveedor creado correctamente',
                'code' => 201,
                'data' => true,
            ]),
            'api.example.test/api/farma/proveedores/buscar/8' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    'id' => 8,
                    'nombre' => 'Farmacéutica Uno',
                    'telefono' => '2233-4455',
                    'direccion' => 'Tegucigalpa',
                    'tiene_imagen' => true,
                ],
            ]),
            'api.example.test/api/farma/proveedores/imagen/8' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => ['image' => $image],
            ]),
            'api.example.test/api/farma/proveedores/actualizar/8' => Http::response([
                'message' => 'Proveedor actualizado correctamente',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/farma/proveedores/eliminar/8' => Http::response([
                'message' => 'Proveedor eliminado correctamente',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        Livewire::test(Proveedores::class)
            ->call('viewImage', 8)
            ->assertDispatched('image-viewer:show')
            ->call('openCreate')
            ->set('nombre', '<b>Proveedor Norte</b>')
            ->set('telefono', '9988-7766')
            ->set('direccion', "San Pedro Sula\x00")
            ->set('imagenArchivo', UploadedFile::fake()->image('logo.png', 50, 50))
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('sweetalert:toast')
            ->call('openEdit', 8)
            ->assertSet('nombre', 'Farmacéutica Uno')
            ->assertSet('imagenExistente', fn (?string $value): bool => str_starts_with((string) $value, 'data:image/png;base64,'))
            ->set('telefono', '2222-1111')
            ->call('save')
            ->assertHasNoErrors()
            ->call('confirmDelete', 8)
            ->call('delete')
            ->assertSet('showDeleteModal', false)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/farma/proveedores/crear'
            && $request->data()['nombre'] === 'Proveedor Norte'
            && is_string($request->data()['imagen'] ?? null)
            && strlen(base64_decode($request->data()['imagen'], true)) > 0);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/farma/proveedores/actualizar/8'
            && $request->data()['id'] === 8
            && $request->data()['telefono'] === '2222-1111'
            && ! array_key_exists('imagen', $request->data()));
    }

    public function test_oversized_provider_image_shows_a_message_without_breaking_the_form(): void
    {
        $this->fakeProviders();

        Livewire::test(Proveedores::class)
            ->call('openCreate')
            ->set('imagenArchivo', UploadedFile::fake()->image('grande.png', 40, 40)->size(2049))
            ->assertSet('imagenArchivo', null)
            ->assertHasErrors(['imagenArchivo'])
            ->assertSee('La imagen no puede exceder 2 MB.')
            ->assertDispatched('sweetalert:alert')
            ->assertSet('showFormModal', true);

        $this->get(route('farma.proveedores'))
            ->assertOk()
            ->assertSee('máximo 2 MB');
    }

    private function fakeProviders(): void
    {
        Http::fake([
            'api.example.test/api/farma/proveedores/listar*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [[
                    'id' => 8,
                    'nombre' => 'Farmacéutica Uno',
                    'telefono' => '2233-4455',
                    'direccion' => 'Tegucigalpa',
                    'tiene_imagen' => true,
                ]],
                'meta' => ['total' => 31, 'current_page' => 1, 'last_page' => 2],
            ]),
        ]);
    }

    private function pngBase64(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $this->assertIsString($png);

        return base64_encode($png);
    }

    /** @return array<int, array<string, mixed>> */
    private function allProviderPermissions(): array
    {
        return [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'prv'],
                'accionesi' => collect([
                    'prvbtn01',
                    'prvbtn02',
                    'prvbtn03',
                    'prvbtn04',
                    'prvbtn05',
                    'prvbtn06',
                ])->map(fn (string $code): array => ['codigo' => $code])->all(),
            ]],
        ]];
    }
}
