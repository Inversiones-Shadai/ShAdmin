<?php

namespace Tests\Feature\Farma;

use App\Livewire\Farma\VentasPerdidas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LostSaleReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_applies_modal_filters_and_dates_only_on_submit(): void
    {
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'test-token');
        Http::preventStrayRequests();
        Http::fake(['api.example.test/api/farma/reportes/ventas-perdidas*' => Http::response([
            'code' => 200, 'data' => [[
                'producto_id' => 8, 'codigo' => 'PRD-8', 'producto' => 'Producto de prueba', 'registros' => 17,
                'stock_id' => 2, 'stock' => 'Sucursal Norte', 'usuario_pos_id' => 5, 'usuario' => 'Operador de prueba',
                'ultimo_registro' => '2026-11-11 19:00:00',
            ]], 'meta' => ['current_page' => 1, 'last_page' => 1],
        ])]);
        $this->get(route('farma.reportes.ventas-perdidas'))->assertForbidden();
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => [['vistai' => ['codigo' => 'vpd'], 'accionesi' => [['codigo' => 'vpdbtn01']]]],
        ]]);
        $this->get(route('farma.reportes.ventas-perdidas'))->assertOk()->assertSee('Ventas perdidas');
        Livewire::test(VentasPerdidas::class)
            ->assertSee('Producto de prueba')->assertSee('11/11/2026 13:00')
            ->assertSee('Sucursal Norte')->assertSee('Operador de prueba')
            ->call('openFilters')->assertSet('showFiltersModal', true)
            ->set('stockId', '2')->set('productId', '8')->set('userId', '5')
            ->set('dateFrom', '2026-11-11')->set('dateTo', '2026-11-10')
            ->assertSet('filters', [])
            ->call('applyFilters')->assertHasErrors(['dateTo' => 'after_or_equal'])
            ->assertSet('showFiltersModal', true)
            ->set('dateTo', '2026-11-11')
            ->call('applyFilters')->assertHasNoErrors()
            ->assertSet('showFiltersModal', false)
            ->assertSet('filters', ['stock_id' => '2', 'producto_id' => '8', 'usuario_pos_id' => '5',
                'fecha_desde' => '2026-11-11', 'fecha_hasta' => '2026-11-11'])
            ->call('openFilters')->assertSet('dateFrom', '2026-11-11')->assertSet('userId', '5')
            ->call('clearFilters')->assertSet('dateFrom', '')->assertSet('userId', '')
            ->set('showFiltersModal', false)
            ->call('openFilters')->assertSet('dateFrom', '2026-11-11')->assertSet('userId', '5')
            ->call('clearFilters')->call('applyFilters')->assertSet('filters', []);

        Http::assertSent(function (Request $request): bool {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return $request->hasHeader('Action-Btn', 'vpdbtn01')
                && $query === ['page' => '1', 'stock_id' => '2', 'producto_id' => '8', 'usuario_pos_id' => '5',
                    'fecha_desde' => '2026-11-11', 'fecha_hasta' => '2026-11-11'];
        });
    }
}
