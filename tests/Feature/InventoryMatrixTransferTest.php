<?php

namespace Tests\Feature;

use App\Livewire\Farma\MatrizInventario;
use App\Livewire\Farma\Transferencias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryMatrixTransferTest extends TestCase
{
    use RefreshDatabase;

    private bool $destinationAvailable = true;

    private bool $failNextSend = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'test-token');
        session()->put('auth.api.spermisos', [['modulo_l' => ['codigo' => 'frm'], 'vistas_l' => [
            ['vistai' => ['codigo' => 'miv'], 'accionesi' => [['codigo' => 'mivbtn01'], ['codigo' => 'mivbtn02']]],
            ['vistai' => ['codigo' => 'trf'], 'accionesi' => array_map(fn (int $id): array => ['codigo' => 'trfbtn0'.$id], [1, 2, 3, 4])],
        ]]]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            '*/farma/stocks/matriz-inventario/stocks' => Http::response(['code' => 200, 'data' => [
                ['id' => 2, 'descripcion' => 'Destino'], ['id' => 3, 'descripcion' => 'Otro stock'],
            ]]),
            '*/farma/stocks/matriz-inventario/listar*' => function (Request $request) {
                $page = (int) ($request['page'] ?? 1);

                return Http::response(['code' => 200, 'data' => $page === 1 ? [$this->product(1, 101, 5), $this->product(2, 102, 2)] : [$this->product(3, 103, 1)],
                    'meta' => ['total' => 3, 'current_page' => $page, 'last_page' => 2]]);
            },
            '*/farma/transferencias/listar*' => Http::response(['code' => 200, 'data' => []]),
            '*/farma/transferencias/opciones*' => fn () => Http::response(['code' => 200, 'data' => [
                'stock_actual' => ['id' => 1, 'descripcion' => 'Bodega origen'],
                'stocks_destino' => $this->destinationAvailable ? [['id' => 2, 'descripcion' => 'Destino']] : [],
                'tipos' => [['id' => 1, 'descripcion' => 'Reposición']],
            ]]),
            '*/farma/transferencias/lotes*' => function (Request $request) {
                $productId = (int) $request['producto_id'];

                return Http::response(['code' => 200, 'data' => $productId === 101 ? [
                    $this->lot(11, 101, 3), $this->lot(12, 101, 10),
                ] : [$this->lot(21, $productId, 10)]]);
            },
            '*/farma/transferencias/enviar' => function () {
                if ($this->failNextSend) {
                    $this->failNextSend = false;

                    return Http::response(['code' => 422, 'message' => 'Existencia insuficiente', 'data' => []], 422);
                }

                return Http::response(['code' => 201, 'data' => ['id' => 99]], 201);
            },
        ]);
        $this->fakeSelectorsFromLegacy(['transfer-stocks' => 'farma/transferencias/opciones|stocks_destino']);
    }

    public function test_selected_matrix_products_open_a_transfer_with_multiple_lots_and_only_write_on_send(): void
    {
        $token = $this->prepareDraft();
        $component = Livewire::withQueryParams(['matriz' => $token])->test(Transferencias::class)
            ->assertSet('showSendModal', true)->assertSet('matrixStockId', 2)->assertSet('stockPara', '2')
            ->assertCount('matrixProducts', 2)->assertSee('Bodega origen')
            ->set('tipo', '1')->call('createHeader')->assertSet('isPrepared', true)
            ->assertSee('data-test="transfer-matrix-products"', false)
            ->assertSee('Producto 101')->assertSee('Producto 102')
            ->call('openMatrixProduct', 101)->assertCount('availableLotsForProduct', 2)
            ->assertSet('lotQuantities.11', 3)->assertSet('lotQuantities.12', 5)
            ->set('lotQuantities.11', 2)->call('selectLotFromModal', 11)
            ->assertViewHas('matrixLines', fn (array $lines): bool => $lines[101]['asignado'] === 2 && $lines[101]['pendiente'] == 3)
            ->call('openMatrixProduct', 101)->assertCount('availableLotsForProduct', 1)
            ->assertSet('lotQuantities.12', 3)->call('selectLotFromModal', 12)
            ->call('openMatrixProduct', 102)->set('lotQuantities.21', 1)->call('selectLotFromModal', 21)
            ->assertCount('details', 3)
            ->assertViewHas('matrixLines', fn (array $lines): bool => $lines[101]['pendiente'] == 0 && $lines[102]['pendiente'] == 1);
        $this->assertNull(session('inventory-matrix.transfer-drafts.'.$token));
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');

        $component->call('finishTransfer')->assertHasNoErrors()->assertSet('showSendModal', false)
            ->assertSet('matrixStockId', null)->assertSet('matrixProducts', [])
            ->assertDispatched('inventory-lots-updated')->call('finishTransfer');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->data() === [
            'tipo' => 1, 'stock_para' => 2, 'detalles' => [
                ['lote' => 11, 'cantidad' => 2], ['lote' => 12, 'cantidad' => 3], ['lote' => 21, 'cantidad' => 1],
            ],
        ]);
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => $request->method() === 'POST'));
    }

    public function test_selection_survives_search_and_pages_but_is_cleared_when_stock_changes(): void
    {
        Livewire::test(MatrizInventario::class)->set('stockId', '2')
            ->call('toggleProduct', 1)->set('search', 'Producto')
            ->call('goToPage', 2)->call('selectPage')->assertCount('selectedProducts', 2)
            ->call('goToPage', 1)->call('toggleProduct', 1)->assertCount('selectedProducts', 1)
            ->set('stockId', '3')->assertSet('selectedProducts', []);
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
    }

    public function test_a_stock_and_products_are_required_and_rows_from_other_stocks_are_rejected(): void
    {
        Livewire::test(MatrizInventario::class)->call('prepareTransfer')->assertHasErrors(['stockId', 'selectedProducts'])
            ->set('stockId', '2')->call('prepareTransfer')->assertHasErrors('selectedProducts');
        Livewire::test(MatrizInventario::class)->set('stockId', '3')->call('toggleProduct', 1)->assertStatus(422);
        $this->assertEmpty(session('inventory-matrix.transfer-drafts', []));
    }

    public function test_matrix_actions_require_transfer_permissions(): void
    {
        $permissions = session('auth.api.spermisos');
        $permissions[0]['vistas_l'][1]['accionesi'] = [['codigo' => 'trfbtn01']];
        session()->put('auth.api.spermisos', $permissions);
        Livewire::test(MatrizInventario::class)->assertDontSee('matrix-create-transfer')->set('stockId', '2')
            ->call('toggleProduct', 1)->assertForbidden();
        Livewire::test(MatrizInventario::class)->call('prepareTransfer')->assertForbidden();
    }

    public function test_selected_matrix_rows_cannot_be_forged_from_the_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(MatrizInventario::class)->set('selectedProducts', [999 => ['id' => 999]]);
    }

    public function test_matrix_destination_is_checked_before_preparing_and_before_sending(): void
    {
        $token = $this->prepareDraft();
        $component = Livewire::withQueryParams(['matriz' => $token])->test(Transferencias::class)
            ->set('tipo', '1')->set('stockPara', '3')->call('createHeader')->assertHasErrors('stockPara')
            ->assertSet('isPrepared', false)->set('stockPara', '2')->call('createHeader')
            ->call('openMatrixProduct', 101)->call('selectLotFromModal', 11);
        $component->set('stockPara', '3')->call('finishTransfer')->assertHasErrors('stockPara')->assertSet('showSendModal', true);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_invalid_lot_quantities_are_rejected_without_losing_the_preparation(): void
    {
        $token = $this->prepareDraft();
        $component = Livewire::withQueryParams(['matriz' => $token])->test(Transferencias::class)
            ->set('tipo', '1')->call('createHeader')->call('openMatrixProduct', 101);
        foreach ([0, -1, 1.5, 4] as $quantity) {
            $component->set('lotQuantities.11', $quantity)->call('selectLotFromModal', 11)
                ->assertHasErrors('lotQuantities.11')->assertSet('details', [])->assertSet('showLotSelectionModal', true);
        }
        $component->set('lotQuantities.11', 2)->call('selectLotFromModal', 11)
            ->assertCount('details', 1)->call('closeSend')->assertSet('matrixProducts', []);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_unselected_products_are_rejected_and_the_draft_is_scoped_to_the_session(): void
    {
        $token = $this->prepareDraft();
        Livewire::withQueryParams(['matriz' => $token])->test(Transferencias::class)
            ->set('tipo', '1')->call('createHeader')->call('openMatrixProduct', 999)->assertStatus(422);
        Livewire::withQueryParams(['matriz' => $token])->test(Transferencias::class)
            ->assertSet('showSendModal', false)->assertSet('matrixProducts', [])->assertDispatched('sweetalert:alert');
    }

    public function test_same_origin_or_unavailable_destination_does_not_open_a_transfer(): void
    {
        $token = $this->prepareDraft();
        $this->destinationAvailable = false;
        Livewire::withQueryParams(['matriz' => $token])->test(Transferencias::class)
            ->assertSet('showSendModal', false)->assertSet('matrixProducts', [])->assertDispatched('sweetalert:alert');
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_a_failed_send_preserves_the_matrix_products_and_lots_for_retry(): void
    {
        $token = $this->prepareDraft();
        $this->failNextSend = true;
        Livewire::withQueryParams(['matriz' => $token])->test(Transferencias::class)
            ->set('tipo', '1')->call('createHeader')->call('openMatrixProduct', 101)->call('selectLotFromModal', 11)
            ->call('finishTransfer')->assertSet('showSendModal', true)->assertCount('details', 1)
            ->assertCount('matrixProducts', 2)->assertSet('matrixStockId', 2)->assertDispatched('sweetalert:alert')
            ->call('finishTransfer')->assertSet('showSendModal', false)->assertSet('matrixProducts', []);
    }

    private function prepareDraft(): string
    {
        $component = Livewire::test(MatrizInventario::class)->set('stockId', '2')->call('selectPage')
            ->assertCount('selectedProducts', 2)->call('prepareTransfer')->assertHasNoErrors();
        $drafts = session('inventory-matrix.transfer-drafts');
        $token = array_key_first($drafts);
        $component->assertRedirect(route('farma.transferencias', ['matriz' => $token]));

        return $token;
    }

    private function product(int $id, int $productId, int $shortage): array
    {
        return ['id' => $id, 'stock_id' => 2, 'producto_id' => $productId, 'stock_nombre' => 'Destino',
            'codigo' => 'P'.$productId, 'descripcion' => 'Producto '.$productId, 'minimo' => 10,
            'maximo' => 20, 'existencia_total' => 10 - $shortage, 'punto_reorden' => $shortage];
    }

    private function lot(int $id, int $productId, int $quantity): array
    {
        return ['id' => $id, 'producto_id' => $productId, 'lote' => 'LOTE-'.$id, 'cantidad' => $quantity,
            'costo' => 2, 'isv' => false, 'fecha_expiracion' => '2027-12-31',
            'producto' => ['id' => $productId, 'codigo' => 'P'.$productId, 'descripcion' => 'Producto '.$productId]];
    }
}
