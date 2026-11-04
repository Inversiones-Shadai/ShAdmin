<?php

namespace Tests\Feature;

use App\Livewire\Farma\MatrizInventario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'test-token');
        $this->permissions([1, 2]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'api.example.test/api/farma/stocks/matriz-inventario/stocks' => Http::response(['code' => 200, 'data' => [['id' => 2, 'descripcion' => 'TEST stock']]]),
            'api.example.test/api/farma/stocks/matriz-inventario/listar*' => Http::response(['code' => 200, 'data' => [
                ['id' => 1, 'stock_nombre' => 'TEST stock', 'codigo' => 'TEST', 'descripcion' => 'TEST producto <script>bad</script>',
                    'minimo' => '3.0000', 'maximo' => '20.0000', 'existencia_total' => '2.0000', 'punto_reorden' => '1.0000',
                    'updated_at' => '2026-10-16 12:00:00', 'existencias_actualizadas_at' => '2026-10-16 12:05:00'],
            ], 'meta' => ['total' => 51, 'current_page' => 1, 'last_page' => 2]]),
        ]);
    }

    public function test_farma_stocks_menu_and_screen_require_matrix_permission_and_escape_product_names(): void
    {
        $this->get(route('farma.stocks.matriz-inventario'))->assertOk()->assertSee('Matriz de inventario')
            ->assertSee('Punto de reorden')->assertSee('Existencia total')->assertSee('2.0000')->assertSee('1.0000')
            ->assertSee('TEST producto &lt;script&gt;bad&lt;/script&gt;', false)->assertDontSee('<script>bad</script>', false);
        $this->get(route('dashboard'))->assertOk()->assertSee('permission-view-frm-miv');
        $this->permissions([]);
        $this->get(route('farma.stocks.matriz-inventario'))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee('permission-view-frm-miv');
    }

    public function test_filters_are_preserved_across_pages_and_only_query_the_matrix(): void
    {
        $component = Livewire::test(MatrizInventario::class)
            ->assertDontSee('Aplicar filtros')->assertDontSee('Quitar filtros')
            ->assertSee('data-remote-select="inventory-stocks"', false)
            ->set('stockId', '2')->assertSet('filters', ['stock_id' => '2']);
        $stockRequest = Http::recorded()->last()[0];
        $this->assertEquals(['page' => 1, 'stock_id' => '2'], $stockRequest->data());
        $component->set('search', ' TEST ')->assertSet('filters', ['stock_id' => '2', 'buscar' => 'TEST'])
            ->call('goToPage', 2)->assertHasNoErrors();
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/matriz-inventario/listar')
            && $r['page'] === 2 && $r['stock_id'] === '2' && $r['buscar'] === 'TEST' && $r->hasHeader('Action-Btn', 'mivbtn01'));
        $component->set('search', '')->assertSet('filters', ['stock_id' => '2'])->assertSet('page', 1)
            ->set('stockId', '')->assertSet('filters', [])->assertHasNoErrors();
        $request = Http::recorded()->last()[0];
        $this->assertEquals(['page' => 1], $request->data());
        Http::assertNotSent(fn (Request $r): bool => $r->method() !== 'GET');
    }

    public function test_filter_permission_is_independent_from_view(): void
    {
        $this->permissions([1]);
        Livewire::test(MatrizInventario::class)->assertDontSee('Producto o código')->set('stockId', '2')->assertForbidden();
        Livewire::test(MatrizInventario::class)->set('search', 'TEST')->assertForbidden();
    }

    private function permissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [['modulo_l' => ['codigo' => 'frm'], 'vistas_l' => [['vistai' => ['codigo' => 'miv'],
            'accionesi' => array_map(fn (int $i): array => ['codigo' => 'mivbtn0'.$i], $actions)]]]]);
    }
}
