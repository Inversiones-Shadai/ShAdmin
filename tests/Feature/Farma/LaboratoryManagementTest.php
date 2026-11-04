<?php

namespace Tests\Feature\Farma;

use App\Livewire\Farma\Laboratorios;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LaboratoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.laboratory_path' => '/farma/laboratorios',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', $this->permissions());
        Http::preventStrayRequests();
    }

    public function test_it_lists_laboratories_and_adds_the_view_to_farma(): void
    {
        $this->fakeList();

        Livewire::test(Laboratorios::class)
            ->assertSee('Laboratorio Central')
            ->assertSee('data-test="view-laboratory-image-button"', false)
            ->assertSet('total', 1);

        $this->get(route('farma.laboratorios'))->assertOk()->assertSee('Laboratorios');
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="permission-view-frm-lbt"', false);
    }

    public function test_it_manages_laboratories_and_images(): void
    {
        $this->fakeList();
        $image = $this->pngBase64();

        Http::fake([
            'api.example.test/api/farma/laboratorios/crear' => Http::response([
                'message' => 'Laboratorio creado correctamente.', 'code' => 201, 'data' => true,
            ]),
            'api.example.test/api/farma/laboratorios/buscar/4' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [
                    'id' => 4,
                    'nombre' => 'Laboratorio Central',
                    'telefono' => null,
                    'direccion' => null,
                    'tiene_imagen' => true,
                ],
            ]),
            'api.example.test/api/farma/laboratorios/imagen/4' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => ['image' => $image],
            ]),
            'api.example.test/api/farma/laboratorios/actualizar/4' => Http::response([
                'message' => 'Laboratorio actualizado correctamente.', 'code' => 200, 'data' => true,
            ]),
            'api.example.test/api/farma/laboratorios/eliminar/4' => Http::response([
                'message' => 'Laboratorio eliminado correctamente.', 'code' => 200, 'data' => true,
            ]),
        ]);

        Livewire::test(Laboratorios::class)
            ->call('viewImage', 4)
            ->assertDispatched('image-viewer:show')
            ->call('openCreate')
            ->set('nombre', '<b>Laboratorio Norte</b>')
            ->set('imagenArchivo', UploadedFile::fake()->image('logo.jpg', 40, 40))
            ->call('save')
            ->assertHasNoErrors()
            ->call('openEdit', 4)
            ->assertSet('nombre', 'Laboratorio Central')
            ->set('telefono', '2233-4455')
            ->call('save')
            ->assertHasNoErrors()
            ->call('confirmDelete', 4)
            ->call('delete')
            ->assertSet('showDeleteModal', false);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->data()['nombre'] === 'Laboratorio Norte'
            && is_string($request->data()['imagen'] ?? null));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->data()['id'] === 4
            && $request->data()['telefono'] === '2233-4455');
    }

    public function test_oversized_laboratory_image_shows_a_message_without_breaking_the_form(): void
    {
        $this->fakeList();

        Livewire::test(Laboratorios::class)
            ->call('openCreate')
            ->set('imagenArchivo', UploadedFile::fake()->image('grande.jpg', 40, 40)->size(2049))
            ->assertSet('imagenArchivo', null)
            ->assertHasErrors(['imagenArchivo'])
            ->assertSee('La imagen no puede exceder 2 MB.')
            ->assertDispatched('sweetalert:alert')
            ->assertSet('showFormModal', true);

        $this->get(route('farma.laboratorios'))
            ->assertOk()
            ->assertSee('máximo 2 MB');
    }

    private function fakeList(): void
    {
        Http::fake([
            'api.example.test/api/farma/laboratorios/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [[
                    'id' => 4,
                    'nombre' => 'Laboratorio Central',
                    'telefono' => '',
                    'direccion' => '',
                    'tiene_imagen' => true,
                ]],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
        ]);
    }

    private function pngBase64(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image, null, 9, PNG_ALL_FILTERS);
        $png = ob_get_clean();
        imagedestroy($image);
        $this->assertIsString($png);

        return base64_encode($png);
    }

    /** @return array<int, array<string, mixed>> */
    private function permissions(): array
    {
        return [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'lbt'],
                'accionesi' => collect([
                    'lbtbtn01', 'lbtbtn02', 'lbtbtn03',
                    'lbtbtn04', 'lbtbtn05', 'lbtbtn06',
                ])->map(fn (string $code): array => ['codigo' => $code])->all(),
            ]],
        ]];
    }
}
