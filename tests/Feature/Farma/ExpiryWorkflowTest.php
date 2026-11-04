<?php

namespace Tests\Feature\Farma;

use App\Livewire\Farma\ReglasProveedores;
use App\Livewire\Farma\Vencimientos;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ExpiryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private bool $productsFail = false;

    private bool $verificationFails = false;

    private int $verificationStatus = 503;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        $this->permissions(['vncbtn01', 'vncbtn02', 'vncbtn03'], ['rdpbtn01', 'rdpbtn02']);
        Http::fake([
            '*/farma/vencimientos/productos*' => fn () => $this->productsFail
                ? Http::response(['code' => 503, 'message' => 'Consulta no disponible'], 503)
                : $this->page([
                    ['producto_id' => 4, 'codigo' => 'P-004', 'codigobar' => '75000004', 'descripcion' => 'Amoxicilina', 'tiene_imagen' => true, 'lotes_count' => 1, 'proxima_expiracion' => '2027-01-20'],
                    ['producto_id' => 5, 'codigo' => 'P-005', 'codigobar' => '75000005', 'descripcion' => 'Ibuprofeno', 'tiene_imagen' => false, 'lotes_count' => 1, 'proxima_expiracion' => '2027-01-20'],
                ]),
            '*/farma/vencimientos/producto/4/lotes*' => $this->page([$this->lot(8, 4)]),
            '*/farma/vencimientos/producto/5/lotes*' => $this->page([$this->lot(9, 5)]),
            '*/farma/vencimientos/verificar' => function (Request $request) {
                if ($this->verificationFails) {
                    return Http::response(['code' => $this->verificationStatus, 'message' => 'No se pudo verificar'], $this->verificationStatus);
                }

                return Http::response(['code' => 200, 'data' => [
                    'verificado_el' => '2026-10-20T12:00:00-06:00',
                    'resultados' => array_map(fn (array $item): array => [...$this->lot($item['id'], 4),
                        'estado' => $item['id'] === 9 ? 'no_apto' : 'apto',
                        'fecha_limite_envio' => '2026-10-20',
                        'motivos' => ['Resultado de las reglas del proveedor'],
                        'regla' => ['meses_anticipacion' => 3, 'notas' => 'Coordinar el envío', 'acepta_hijos' => true, 'tipo_devolucion' => 'directa'],
                    ], $request->data()['lotes']),
                ]]);
            },
            '*/farma/reglas-proveedores/listar*' => $this->page([['id' => 1, 'nombre' => 'Proveedor A', 'revision' => null, 'meses_anticipacion' => null, 'tipo_devolucion' => null]]),
            '*/farma/reglas-proveedores/1' => fn (Request $request) => Http::response(['code' => 200, 'message' => 'Reglas guardadas',
                'data' => ['proveedor' => ['id' => 1, 'nombre' => 'Proveedor A'], 'regla' => null]]),
        ]);
    }

    public function test_screens_show_product_images_search_and_separate_supplier_rules_navigation(): void
    {
        $this->get(route('farma.vencimientos'))->assertOk()->assertSee('Amoxicilina')->assertSee('75000004')
            ->assertSee(route('farma.products.preview', ['product' => 4]), false)->assertSee('Reglas de proveedores');
        $this->get(route('farma.reglas-proveedores'))->assertOk()->assertSee('Proveedor A')->assertSee('Sin configurar');
        Livewire::test(Vencimientos::class)->set('search', '75000004')->set('months', '6')->assertSet('page', 1);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/vencimientos/productos')
            && ($request['search'] ?? '') === '75000004' && $request->hasHeader('Action-Btn', 'vncbtn01'));
        Http::assertSent(fn (Request $request): bool => ($request['meses'] ?? '') === 6);
    }

    public function test_lots_from_multiple_searches_are_deduplicated_and_verified_using_only_ids(): void
    {
        $component = Livewire::test(Vencimientos::class)->call('openLots', 4)->call('addLot', 8)->call('addLot', 8)
            ->assertCount('selectedLots', 1)->set('search', 'Ibuprofeno')->call('openLots', 5)->call('addLot', 9)
            ->assertCount('selectedLots', 2)
            ->call('verify')->assertHasNoErrors()->assertCount('results', 2)->assertSet('results.0.estado', 'apto')
            ->assertSee('20/10/2026')->assertSee('Coordinar el envío')->assertSee('Acepta productos hijos / empezados')->assertDontSee('Acepta productos empezados')
            ->assertSee('Es bonificación')->assertSee('Sí · No acepta devolución')->assertDontSee('FA-10')->assertDontSee('Compra en escala');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/vencimientos/verificar')
            && $request->hasHeader('Action-Btn', 'vncbtn03')
            && $request->data() === ['lotes' => [['id' => 8], ['id' => 9]]]);
        $component->call('removeLot', 8)->assertSet('results', [])->assertSet('verifiedAt', null)
            ->assertCount('selectedLots', 1)->call('clearSelection')->assertSet('selectedLots', []);
    }

    public function test_failed_queries_clear_stale_products_and_verification_results_without_losing_selected_lots(): void
    {
        $component = Livewire::test(Vencimientos::class)->call('openLots', 4)->call('addLot', 8)->call('verify')->assertCount('results', 1);
        $this->verificationFails = true;
        $component->call('verify')->assertSet('results', [])->assertSet('verifiedAt', null)->assertCount('selectedLots', 1)->assertSee('No se pudo verificar');
        $this->productsFail = true;
        $component->set('search', 'otro')->assertSet('products', [])->assertSet('total', 0)->assertCount('selectedLots', 1);
    }

    public function test_verification_needs_no_manual_opened_state_and_unknown_lot_ids_are_not_added(): void
    {
        Livewire::test(Vencimientos::class)->call('openLots', 4)->call('addLot', 999)->assertCount('selectedLots', 0)
            ->call('addLot', 8)->assertSee('Padre / completo')->assertDontSee('Cerrado / completo')
            ->call('verify')->assertSet('results.0.estado', 'apto');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/vencimientos/verificar')
            && $request->data()['lotes'] === [['id' => 8]]);
    }

    public function test_rules_are_explicitly_configured_and_saved_without_purchase_payment_fields(): void
    {
        Livewire::test(ReglasProveedores::class)->call('openPolicy', 1)->assertSet('revision', 0)->assertSet('returnType', '')
            ->call('save')->assertHasErrors(['returnType'])->set('months', '4')->set('acceptsChildren', true)
            ->assertSee('Acepta productos hijos / empezados')->assertDontSee('Acepta productos empezados')
            ->set('returnType', 'autorizacion')->set('notes', 'Coordinar con el proveedor')
            ->call('save')->assertHasNoErrors()->assertSet('showForm', false);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && str_ends_with($request->url(), '/reglas-proveedores/1')
            && $request->hasHeader('Action-Btn', 'rdpbtn02') && $request->data() === [
                'meses_anticipacion' => 4, 'acepta_hijos' => true,
                'tipo_devolucion' => 'autorizacion', 'notas' => 'Coordinar con el proveedor', 'revision' => 0,
            ]);
    }

    public function test_permission_checks_block_direct_routes_and_livewire_actions_independently(): void
    {
        $this->permissions([], []);
        $this->get(route('farma.vencimientos'))->assertForbidden();
        $this->get(route('farma.reglas-proveedores'))->assertForbidden();
        $this->permissions(['vncbtn01', 'vncbtn02'], ['rdpbtn01']);
        Livewire::test(Vencimientos::class)->call('openLots', 4)->call('addLot', 8)->call('verify')->assertForbidden();
        Livewire::test(ReglasProveedores::class)->call('openPolicy', 1)->call('save')->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => in_array($request->method(), ['POST', 'PUT'], true));
    }

    public function test_session_expiry_redirects_to_login(): void
    {
        $component = Livewire::test(Vencimientos::class)->call('openLots', 4)->call('addLot', 8);
        $this->verificationFails = true;
        $this->verificationStatus = 401;
        $component->call('verify')->assertRedirect(route('login'));
    }

    private function permissions(array $expiry, array $rules): void
    {
        session()->forget('auth.api.abilities');
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => [
                ['vistai' => ['codigo' => 'vnc'], 'accionesi' => array_map(fn (string $code): array => ['codigo' => $code], $expiry)],
                ['vistai' => ['codigo' => 'rdp'], 'accionesi' => array_map(fn (string $code): array => ['codigo' => $code], $rules)],
                ['vistai' => ['codigo' => 'prd'], 'accionesi' => [['codigo' => 'prdbtn06']]],
            ],
        ]]);
    }

    private function page(array $items): PromiseInterface
    {
        return Http::response(['code' => 200, 'data' => $items, 'meta' => ['total' => count($items), 'current_page' => 1, 'last_page' => 1]]);
    }

    private function lot(int $id, int $product): array
    {
        return ['id' => $id, 'producto_id' => $product, 'lote' => 'L-'.$id, 'descripcion' => $product === 4 ? 'Amoxicilina' : 'Ibuprofeno',
            'codigo' => 'P-'.$product, 'fecha_expiracion' => '2027-01-20', 'proveedor_id' => 1, 'proveedor_nombre' => 'Proveedor A',
            'compra_id' => 10, 'factura' => 'FA-10', 'es_bonificacion' => $id === 9, 'es_hijo' => false];
    }
}
