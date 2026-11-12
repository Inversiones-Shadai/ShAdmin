<?php

namespace Tests\Feature;

use App\Livewire\Farma\AlertasVenta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SaleAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'test-token');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.example.test/api/farma/alertas-venta*' => fn (Request $request) => $request->method() === 'GET'
            ? Http::response(['code' => 200, 'data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1]])
            : Http::response(['code' => 201, 'data' => ['id' => '1']], 201)]);
    }

    public function test_manager_saves_ordered_requirements_for_one_category_and_one_stock(): void
    {
        $this->permissions(['avtbtn01', 'avtbtn02']);
        $this->get(route('farma.alertas-venta'))->assertOk()->assertSee('Alertas de venta');
        Livewire::test(AlertasVenta::class)->call('start')
            ->assertSee('Todos los stocks')
            ->set('target', 'categoria')->set('categoryId', '11')
            ->set('scope', 'stock')->set('stockId', '7')
            ->set('title', 'Venta controlada')->set('requirements', " Revisar receta\r\n\r\nVerificar identidad ")
            ->call('save')->assertHasNoErrors()->assertSet('editing', false);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->hasHeader('Action-Btn', 'avtbtn02')
            && $request['producto_id'] === null && $request['categoria_id'] === '11'
            && $request['alcance'] === 'stock' && $request['stock_id'] === '7'
            && $request['requisitos'] === ['Revisar receta', 'Verificar identidad']);
    }

    public function test_view_permission_does_not_allow_editing_alerts(): void
    {
        $this->permissions(['avtbtn01']);
        Livewire::test(AlertasVenta::class)->assertDontSee('Nueva alerta')->call('start')->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
    }

    private function permissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [['modulo_l' => ['codigo' => 'frm'], 'vistas_l' => [['vistai' => ['codigo' => 'avt'],
            'accionesi' => array_map(fn (string $code): array => ['codigo' => $code], $actions)]]]]);
    }
}
