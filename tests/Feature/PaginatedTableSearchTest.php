<?php

namespace Tests\Feature;

use App\Livewire\Config\Devices;
use App\Livewire\Config\Stocks;
use App\Livewire\Config\Views;
use App\Livewire\Farma\CompraTransacciones;
use App\Livewire\Farma\Laboratorios;
use App\Livewire\Farma\ProductCatalogManager;
use App\Livewire\Farma\Productos;
use App\Livewire\Farma\Proveedores;
use App\Livewire\Farma\PurchaseCatalogManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaginatedTableSearchTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string, array{class-string, array<string, string>}> */
    public static function tables(): iterable
    {
        foreach ([Productos::class, Laboratorios::class, Proveedores::class, Devices::class,
            Stocks::class, Views::class, CompraTransacciones::class] as $class) {
            yield $class => [$class, []];
        }
        foreach (['unidades', 'estados', 'categorias', 'presentaciones', 'administraciones',
            'familias', 'concentraciones', 'principios-activos'] as $catalog) {
            yield $catalog => [ProductCatalogManager::class, ['catalog' => $catalog]];
        }
        foreach (['tipos-compra', 'tipos-transaccion'] as $catalog) {
            yield $catalog => [PurchaseCatalogManager::class, ['catalog' => $catalog]];
        }
    }

    #[DataProvider('tables')]
    public function test_search_reloads_the_full_catalog_resets_page_and_survives_pagination(string $class, array $parameters): void
    {
        $this->actingAs(User::factory()->create());
        Gate::before(static fn (): bool => true);
        config([
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        session()->put('auth.api.token', 'api-token');
        Http::fake(['api.example.test/*' => function (Request $request) {
            $search = $request['search'] ?? '';
            $page = (int) ($request['page'] ?? 1);
            $found = $search === 'Albendafar';
            $items = $search === 'NoExiste' ? [] : [[
                'id' => $found ? 900 + $page : $page,
                'codigo' => $found ? 'A12345' : 'OTRO',
                'descripcion' => $found ? 'Albendafar Suspension, frasco 20ml' : 'Otro registro',
                'nombre' => $found ? 'Albendafar' : 'Otro registro',
                'displayname' => $found ? 'Albendafar' : 'Otro dispositivo',
                'valor' => $found ? 'Albendafar' : 'Otra concentracion',
                'nro' => $found ? 'Albendafar' : 'Otra transaccion',
            ]];

            return Http::response(['message' => 'ok', 'code' => 200, 'data' => $items,
                'meta' => ['total' => $search === 'NoExiste' ? 0 : ($found ? 31 : 120),
                    'current_page' => $page, 'last_page' => $search === 'NoExiste' ? 1 : ($found ? 2 : 4)]]);
        }]);

        $component = Livewire::test($class, $parameters)->assertSet('total', 120)
            ->call('nextPage')->assertSet('page', 2)
            ->set('search', ' Albendafar ')->assertSet('page', 1)->assertSet('total', 31)
            ->call('nextPage')->assertSet('page', 2)->assertSet('total', 31);
        Http::assertSent(fn (Request $request): bool => ($request['search'] ?? null) === 'Albendafar' && ($request['page'] ?? null) == 2);
        $component->set('search', 'NoExiste')->assertSet('page', 1)->assertSet('total', 0)
            ->set('search', '')->assertSet('page', 1)->assertSet('total', 120);
    }
}
