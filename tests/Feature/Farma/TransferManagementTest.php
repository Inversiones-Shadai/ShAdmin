<?php

namespace Tests\Feature\Farma;

use App\Livewire\Farma\Transferencias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class TransferManagementTest extends TestCase
{
    use RefreshDatabase;

    private bool $failNextSend = false;

    public function test_initial_list_uses_the_current_honduras_day_and_date_range_survives_pagination(): void
    {
        $this->travelTo(now('UTC')->setDateTime(2026, 11, 1, 1, 30));
        Livewire::test(Transferencias::class)
            ->assertSet('dateFrom', '2026-10-31')->assertSet('dateTo', '2026-10-31')
            ->assertSet('dateSummary', '31/10/2026')->assertSee('Filtros')
            ->call('openFilters')->set('dateFrom', '2026-10-01')->set('dateTo', '2026-10-16')
            ->set('direction', 'recibidas')->call('applyFilters')
            ->assertHasNoErrors()->assertSet('showFiltersModal', false)->assertSet('page', 1)
            ->assertSet('dateSummary', '01/10/2026 — 16/10/2026')
            ->call('nextPage')->assertSet('page', 2);
        Http::assertSent(fn (Request $request): bool => ($request['fecha_desde'] ?? null) === '2026-10-31' && ($request['fecha_hasta'] ?? null) === '2026-10-31');
        Http::assertSent(fn (Request $request): bool => ($request['fecha_desde'] ?? null) === '2026-10-01'
            && ($request['fecha_hasta'] ?? null) === '2026-10-16' && ($request['page'] ?? null) === 2 && ($request['direccion'] ?? null) === 'recibidas');
    }

    public function test_filter_edits_wait_for_apply_and_cancel_restores_the_previous_query(): void
    {
        $today = now('America/Tegucigalpa')->toDateString();
        Livewire::test(Transferencias::class)->call('openFilters')
            ->set('dateFrom', '2020-01-01')->set('direction', 'recibidas')->set('search', 'Otro')
            ->assertSet('transfers.0.id', 8)->call('cancelFilters')
            ->assertSet('dateFrom', $today)->assertSet('direction', 'todas')->assertSet('search', '')
            ->assertSet('showFiltersModal', false);
        Http::assertSentCount(1);
    }

    public function test_invalid_dates_do_not_query_the_api_and_empty_dates_allow_all_history(): void
    {
        $component = Livewire::test(Transferencias::class)->call('openFilters')
            ->set('dateFrom', '2026-10-31')->set('dateTo', '2026-10-01')->call('applyFilters')
            ->assertHasErrors('dateTo')->assertSet('showFiltersModal', true);
        Http::assertSentCount(1);
        $component->set('dateFrom', '')->set('dateTo', '')->call('applyFilters')
            ->assertHasNoErrors()->assertSet('dateSummary', 'Todo el historial')
            ->call('openFilters')->call('resetFiltersToToday')
            ->assertSet('dateFrom', now('America/Tegucigalpa')->toDateString())
            ->assertSet('dateTo', now('America/Tegucigalpa')->toDateString());
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/transferencias/listar')
            && ! array_key_exists('fecha_desde', $request->data()) && ! array_key_exists('fecha_hasta', $request->data()));
    }

    private function fakeConversion(bool $failFirst = false): void
    {
        Http::fake([
            'api.example.test/api/farma/transferencias/conversion/8*' => Http::response([
                'code' => 200, 'message' => 'ok', 'data' => [
                    'lote' => 8, 'disponible' => 15, 'unidades_por_padre' => 100,
                    'hijos' => [['id' => 40, 'codigo' => 'H-40', 'descripcion' => 'Producto hijo']],
                ],
            ]),
            'api.example.test/api/farma/transferencias/convertir' => function () use (&$failFirst) {
                if ($failFirst) {
                    $failFirst = false;
                    throw new ConnectionException('Respuesta perdida');
                }

                return Http::response(['code' => 200, 'message' => 'ok', 'data' => [
                    'lote_padre' => 8, 'disponible_padre' => 13, 'unidades_generadas' => 200,
                    'lote_hijo' => ['id' => 80, 'producto_id' => 40, 'lote' => 'L-008', 'cantidad' => 200, 'costo' => 0.1001, 'isv' => true,
                        'producto' => ['id' => 40, 'codigo' => 'H-40', 'descripcion' => 'Producto hijo']],
                ]]);
            },
        ]);
    }

    public function test_conversion_adds_the_child_and_updates_parent_availability_in_the_draft(): void
    {
        $this->fakeConversion();
        Livewire::test(Transferencias::class)
            ->call('openSend')->set('tipo', '1')->set('stockPara', '2')->call('createHeader')
            ->call('handleProductSelected', 'transfer-detail', ['id' => 4])
            ->call('selectLotFromModal', 8)
            ->call('handleProductSelected', 'transfer-detail', ['id' => 4])
            ->call('openConversion', 8)->assertSee('Convertir padre a hijo')
            ->assertSet('conversionChild', '40')->set('conversionQuantity', 2)
            ->call('convertProduct')->assertSet('pendingConversion', [])
            ->assertSet('selectedLots.8.cantidad', 13)->assertSet('selectedLots.80.costo', 0.1001)
            ->assertSet('details.1', ['lote' => 80, 'cantidad' => 200])
            ->assertSet('showLotSelectionModal', false)->assertDispatched('inventory-lots-updated');
        $this->assertFalse(session()->has('transfers.pending-conversion'));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/convertir')
            && $request->hasHeader('Action-Btn', 'trfbtn04')
            && $request['lote_padre'] === 8 && $request['producto_hijo'] === 40 && $request['cantidad'] === 2
            && Str::isUuid($request['request_id']));
    }

    public function test_conversion_retries_the_same_payload_after_reopening_and_blocks_sending_while_uncertain(): void
    {
        $this->fakeConversion(failFirst: true);
        $component = Livewire::test(Transferencias::class)
            ->call('openSend')->set('tipo', '1')->set('stockPara', '2')->call('createHeader')
            ->call('handleProductSelected', 'transfer-detail', ['id' => 4])
            ->call('openConversion', 8)->set('conversionQuantity', 2)->call('convertProduct')
            ->assertDispatched('sweetalert:alert')->assertCount('details', 0);
        $pending = $component->get('pendingConversion');
        $this->assertNotEmpty($pending);
        $component->set('conversionQuantity', 3)->call('finishTransfer')->assertSet('showLotSelectionModal', true)
            ->call('closeSend')->call('openSend')->assertSet('pendingConversion', $pending)
            ->assertSee('Confirmar conversión pendiente')->call('convertProduct')->assertSet('pendingConversion', [])
            ->assertCount('details', 0)->assertDispatched('inventory-lots-updated');
        $this->assertFalse(session()->has('transfers.pending-conversion'));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/convertir') && $request->data() === $pending);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/enviar'));
    }

    public function test_conversion_rejects_other_children_and_quantities_reserved_in_the_draft(): void
    {
        $this->fakeConversion();
        Livewire::test(Transferencias::class)
            ->call('openSend')->set('tipo', '1')->set('stockPara', '2')->call('createHeader')
            ->call('handleProductSelected', 'transfer-detail', ['id' => 4])
            ->set('lotQuantities.8', 14)->call('selectLotFromModal', 8)
            ->call('handleProductSelected', 'transfer-detail', ['id' => 4])
            ->call('openConversion', 8)->set('conversionChild', '999')
            ->call('convertProduct')->assertHasErrors('conversionChild')
            ->set('conversionChild', '40')->set('conversionQuantity', 2)
            ->call('convertProduct')->assertHasErrors('conversionQuantity');
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_conversion_requires_the_send_permission(): void
    {
        $permissions = session('auth.api.spermisos');
        $permissions[0]['vistas_l'][0]['accionesi'] = [['codigo' => 'trfbtn01']];
        session()->put('auth.api.spermisos', $permissions);
        Livewire::test(Transferencias::class)->call('openConversion', 8)->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => [
                [
                    'vistai' => ['codigo' => 'trf'],
                    'accionesi' => collect(['trfbtn01', 'trfbtn02', 'trfbtn03', 'trfbtn04', 'trfbtn06'])
                        ->map(fn (string $code): array => ['codigo' => $code])->all(),
                ],
                [
                    'vistai' => ['codigo' => 'lts'],
                    'accionesi' => [['codigo' => 'ltsbtn01']],
                ],
            ],
        ]]);
        Http::preventStrayRequests();
        Http::fake([
            'api.example.test/api/farma/transferencias/listar*' => function (Request $request) {
                $page = (int) ($request->data()['page'] ?? 1);
                $incoming = ($request->data()['direccion'] ?? 'todas') === 'recibidas';

                return Http::response([
                    'message' => 'ok', 'code' => 200, 'data' => [[
                        'id' => ($incoming ? 9 : 8) + ($page - 1) * 10,
                        'puede_imprimir' => ! $incoming,
                        'estado_label' => 'ENVIADA', 'estado_recepcion_label' => 'PENDIENTE',
                        'stock_de' => ['descripcion' => 'Origen'], 'stock_para' => ['descripcion' => 'Destino'],
                    ]],
                    'meta' => ['total' => 2, 'current_page' => $page, 'last_page' => 2,
                        'stocks' => [['id' => 1, 'descripcion' => 'Origen'], ['id' => 2, 'descripcion' => 'Destino']],
                        'tipos' => [['id' => 1, 'descripcion' => 'Transferencia interna'], ['id' => 2, 'descripcion' => 'Devolución']]],
                ]);
            },
            'api.example.test/api/farma/transferencias/opciones*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [
                    'tipos' => [['id' => 1, 'descripcion' => 'Transferencia interna']],
                    'stocks_destino' => [['id' => 2, 'descripcion' => 'Destino']],
                ],
            ]),
            'api.example.test/api/farma/transferencias/enviar' => function () {
                if ($this->failNextSend) {
                    $this->failNextSend = false;

                    return Http::response(['message' => 'Existencia insuficiente', 'code' => 422, 'data' => []], 422);
                }

                return Http::response(['message' => 'Enviada', 'code' => 201, 'data' => ['id' => 8]], 201);
            },
            'api.example.test/api/farma/transferencias/lotes*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [
                    ['id' => 8, 'lote' => 'L-008', 'cantidad' => 15, 'costo' => 10.01, 'isv' => true,
                        'fecha_elaboracion' => '2026-01-15', 'fecha_expiracion' => '2028-01-15'],
                    ['id' => 9, 'lote' => 'L-009', 'cantidad' => 0],
                    ['id' => 10, 'lote' => 'L-010', 'cantidad' => 20, 'costo' => 2.50, 'isv' => false],
                ],
            ]),
        ]);
    }

    public function test_direction_filters_the_api_and_is_preserved_while_searching_and_paginating(): void
    {
        Livewire::test(Transferencias::class)
            ->assertSee('Todas')->assertSee('Enviadas')->assertSee('Recibidas')
            ->assertSet('direction', 'todas')->assertSet('transfers.0.id', 8)
            ->call('nextPage')->assertSet('page', 2)
            ->set('direction', 'recibidas')->assertSet('page', 1)->assertSet('transfers.0.id', 9)
            ->set('search', 'Destino')->assertSet('direction', 'recibidas')
            ->call('nextPage')->assertSet('page', 2)->assertSet('transfers.0.id', 19)
            ->set('direction', 'enviadas')->assertSet('page', 1)->assertSet('transfers.0.id', 8)
            ->set('direction', 'todas')->assertSet('search', 'Destino');

        foreach (['todas', 'enviadas', 'recibidas'] as $direction) {
            Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/listar')
                && ($request->data()['direccion'] ?? null) === $direction
                && ($request->data()['search'] ?? null) === 'Destino');
        }
        Http::assertSent(fn (Request $request): bool => ($request->data()['direccion'] ?? null) === 'recibidas'
            && ($request->data()['page'] ?? null) === 2 && ($request->data()['search'] ?? null) === 'Destino');
    }

    public function test_stock_selector_is_hidden_and_forged_changes_are_denied_without_its_permission(): void
    {
        Livewire::test(Transferencias::class)
            ->assertSet('stockFilter', '')
            ->assertCount('transferStocks', 0)
            ->assertDontSee('data-test="transfer-stock-filter"', false)
            ->set('stockFilter', '2')
            ->assertForbidden();

        Http::assertNotSent(fn (Request $request): bool => array_key_exists('stock_id', $request->data()));
    }

    public function test_stock_filter_requires_its_permission_and_survives_direction_search_pagination_and_form_reset(): void
    {
        $permissions = session('auth.api.spermisos');
        $permissions[0]['vistas_l'][0]['accionesi'][] = ['codigo' => 'trfbtn07'];
        session()->put('auth.api.spermisos', $permissions);
        Http::fake([
            'api.example.test/api/farma/transferencias/buscar/8*' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => ['id' => 8, 'detalles' => []],
            ]),
        ]);

        Livewire::test(Transferencias::class)
            ->assertSee('data-test="transfer-stock-filter"', false)
            ->assertSee('Stock de esta sesión')
            ->assertSee('data-remote-select="transfer-filter-stocks"', false)
            ->assertSet('stockFilter', '')
            ->call('nextPage')->assertSet('page', 2)
            ->set('stockFilter', '2')->assertSet('page', 1)
            ->set('typeFilter', '2')->set('direction', 'recibidas')->set('search', 'Destino')
            ->call('nextPage')->assertSet('page', 2)->assertSet('stockFilter', '2')
            ->call('openDetail', 8)->assertSet('showDetailModal', true)
            ->call('closeSend')->assertSet('stockFilter', '2')
            ->set('stockFilter', '')->assertSet('page', 1)->assertSet('showDetailModal', false)
            ->set('stockFilter', '999')->assertSet('stockFilter', '');

        Http::assertSent(fn (Request $request): bool => ($request->data()['stock_id'] ?? null) === 2
            && ($request->data()['direccion'] ?? null) === 'recibidas'
            && ($request->data()['tipo'] ?? null) === 2
            && ($request->data()['search'] ?? null) === 'Destino'
            && ($request->data()['page'] ?? null) === 2
            && $request->hasHeader('Action-Btn', 'trfbtn01'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/buscar/8')
            && ($request->data()['stock_id'] ?? null) === 2);
        Http::assertSent(fn (Request $request): bool => ! array_key_exists('stock_id', $request->data())
            && ($request->data()['direccion'] ?? null) === 'recibidas');
    }

    public function test_stock_options_cannot_be_overwritten_from_the_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(Transferencias::class)->set('transferStocks', [['id' => 999, 'descripcion' => 'Inventado']]);
    }

    public function test_selected_stock_cannot_be_used_after_its_permission_is_revoked(): void
    {
        $permissions = session('auth.api.spermisos');
        $withFilter = $permissions;
        $withFilter[0]['vistas_l'][0]['accionesi'][] = ['codigo' => 'trfbtn07'];
        session()->put('auth.api.spermisos', $withFilter);
        $component = Livewire::test(Transferencias::class)->set('stockFilter', '2');
        session()->put('auth.api.spermisos', $permissions);
        $component->call('nextPage')->assertForbidden();
    }

    public function test_it_opens_lot_product_search_and_adds_an_available_lot(): void
    {

        Livewire::test(Transferencias::class)
            ->call('openSend')
            ->set('tipo', '1')
            ->set('stockPara', '2')
            ->call('createHeader')
            ->assertSee('Agregar producto')
            ->assertDontSee('Buscar producto (con imagen)')
            // A lot ID must not exclude a different product with the same ID.
            ->set('details', [['lote' => 4, 'cantidad' => 1]])
            ->call('openProductSearch')
            ->assertDispatched('advanced-product-search:open', fn (string $name, array $params): bool => $params['context'] === 'transfer-detail' && empty($params['excludedIds']))
            ->call('handleProductSelected', 'transfer-detail', ['id' => 4, 'descripcion' => 'Producto con lotes'])
            ->assertSet('showLotSelectionModal', true)
            ->assertCount('availableLotsForProduct', 2)
            ->set('lotQuantities.8', 3)
            ->call('selectLotFromModal', 8)
            ->assertSet('details.1', ['lote' => 8, 'cantidad' => 3])
            ->assertSet('showLotSelectionModal', false);

        Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/farma/transferencias/lotes')
            && ($request->data()['producto_id'] ?? null) === 4
            && $request->hasHeader('Action-Btn', 'trfbtn03'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/farma/productos/'));
    }

    public function test_type_filter_works_with_list_only_permissions_and_survives_other_filters_and_form_reset(): void
    {
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'frm'], 'vistas_l' => [[
                'vistai' => ['codigo' => 'trf'], 'accionesi' => [['codigo' => 'trfbtn01']],
            ]],
        ]]);
        Livewire::test(Transferencias::class)
            ->assertSee('Todos los tipos')->assertSee('data-remote-select="transfer-filter-types"', false)
            ->call('nextPage')->assertSet('page', 2)
            ->set('typeFilter', '2')->assertSet('page', 1)
            ->set('search', 'Destino')->set('direction', 'recibidas')
            ->call('nextPage')->assertSet('page', 2)->assertSet('typeFilter', '2')
            ->call('closeSend')->assertSet('typeFilter', '2')->assertCount('transferTypes', 2)
            ->set('typeFilter', '')->assertSet('page', 1)
            ->set('typeFilter', '999')->assertSet('typeFilter', '');
        Http::assertSent(fn (Request $request): bool => ($request->data()['tipo'] ?? null) === 2
            && ($request->data()['direccion'] ?? null) === 'recibidas'
            && ($request->data()['search'] ?? null) === 'Destino'
            && ($request->data()['page'] ?? null) === 2);
        Http::assertSent(fn (Request $request): bool => ! array_key_exists('tipo', $request->data())
            && ($request->data()['direccion'] ?? null) === 'recibidas'
            && ($request->data()['page'] ?? null) === 1);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/opciones'));
    }

    public function test_it_calculates_taxed_and_exempt_lots_and_only_writes_when_sent(): void
    {
        $component = Livewire::test(Transferencias::class)
            ->call('openSend')->set('tipo', '1')->set('stockPara', '2')->call('createHeader')
            ->assertSet('isPrepared', true)
            ->call('handleProductSelected', 'transfer-detail', ['id' => 4, 'descripcion' => 'Producto con lotes'])
            ->set('lotQuantities.8', 3)->call('selectLotFromModal', 8)
            ->assertSee('15/01/2026')->assertSee('15/01/2028')
            ->assertSee('Costo unitario')->assertSee('L 30.03')->assertSee('L 4.50')->assertSee('L 34.53')
            ->call('handleProductSelected', 'transfer-detail', ['id' => 4, 'descripcion' => 'Producto con lotes'])
            ->set('lotQuantities.10', 2)->call('selectLotFromModal', 10)
            ->assertSee('Exento')->assertSee('Subtotal general')->assertSee('Subtotal ISV')
            ->assertViewHas('draftTotals', ['subtotal' => 35.03, 'isv' => 4.50, 'total' => 39.53])
            ->assertSee('L 35.03')->assertSee('L 39.53')
            ->set('details.0.cantidad', 4)->assertSee('L 51.04')
            ->set('details.0.cantidad', 3)->assertSee('L 39.53');

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

        $component->call('finishTransfer')
            ->assertDispatched('inventory-lots-updated')
            ->assertHasNoErrors()->assertSet('showSendModal', false)->assertSet('isPrepared', false)
            ->assertSet('details', [])
            ->call('finishTransfer')
            ->call('removeLot', 0)
            ->call('handleProductSelected', 'transfer-detail', ['id' => 4])
            ->assertSet('details', [])->assertSet('showLotSelectionModal', false);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/enviar')
            && $request->data() === ['tipo' => 1, 'stock_para' => 2, 'detalles' => [
                ['lote' => 8, 'cantidad' => 3], ['lote' => 10, 'cantidad' => 2],
            ]]);
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => $request->method() === 'POST'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/actualizar/'));
    }

    public function test_invalid_quantities_and_an_empty_transfer_are_not_sent(): void
    {
        $component = Livewire::test(Transferencias::class)
            ->call('openSend')->set('tipo', '1')->set('stockPara', '2')->call('createHeader')
            ->call('finishTransfer')->assertHasErrors('details')
            ->call('handleProductSelected', 'transfer-detail', ['id' => 4])
            ->call('selectLotFromModal', 8);

        foreach ([0, -1, 1.5, 16] as $quantity) {
            $component->set('details.0.cantidad', $quantity)->call('finishTransfer')
                ->assertHasErrors('details.0.cantidad')->assertSet('showSendModal', true);
        }

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_an_api_failure_keeps_the_transfer_for_retry(): void
    {
        $this->failNextSend = true;

        Livewire::test(Transferencias::class)
            ->call('openSend')->set('tipo', '1')->set('stockPara', '2')->call('createHeader')
            ->call('handleProductSelected', 'transfer-detail', ['id' => 4])
            ->call('selectLotFromModal', 8)
            ->call('finishTransfer')->assertSet('showSendModal', true)->assertCount('details', 1)
            ->assertDispatched('sweetalert:alert')->assertNotDispatched('sweetalert:toast')
            ->assertNotDispatched('inventory-lots-updated')
            ->call('finishTransfer')->assertSet('showSendModal', false)->assertDispatched('sweetalert:toast');
    }

    public function test_sent_transfers_are_read_only_and_display_saved_amounts(): void
    {
        Http::fake([
            'api.example.test/api/farma/transferencias/buscar/8' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [
                    'id' => 8, 'subtotal' => 30.03, 'isvtotal' => 4.50, 'total' => 34.53,
                    'detalles' => [[
                        'id' => 1, 'cantidad' => 3, 'subtotal' => 30.03, 'isv' => 4.50, 'total' => 34.53,
                        'lote_detalle' => ['id' => 8, 'lote' => 'L-008', 'costo' => 999, 'isv' => false,
                            'producto_detalle' => ['descripcion' => 'Producto enviado', 'codigo' => 'P-004']],
                    ]],
                ],
            ]),
        ]);

        Livewire::test(Transferencias::class)
            ->assertDontSee('Editar productos de transferencia')
            ->call('openEdit', 8)
            ->assertSet('showDetailModal', true)->assertSet('showSendModal', false)
            ->assertSee('Producto enviado')->assertSee('L-008')->assertSee('L 10.01')
            ->assertSee('L 30.03')->assertSee('L 4.50')->assertSee('L 34.53')
            ->assertDontSee('L 999.00')->assertDontSee('Enviar transferencia')
            ->assertSee('disponible solo para consulta')
            ->call('finishTransfer')->call('selectLotFromModal', 8)->call('removeLot', 0)
            ->assertSet('details', []);

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_closing_an_unsent_transfer_discards_it_without_writing(): void
    {
        Livewire::test(Transferencias::class)
            ->call('openSend')->set('tipo', '1')->set('stockPara', '2')->call('createHeader')
            ->call('handleProductSelected', 'transfer-detail', ['id' => 4])
            ->call('selectLotFromModal', 8)->call('closeSend')
            ->assertSet('details', [])->assertSet('isPrepared', false)
            ->call('finishTransfer');

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_lot_cost_and_tax_data_cannot_be_changed_from_the_client(): void
    {
        $component = Livewire::test(Transferencias::class)
            ->call('openSend')->set('tipo', '1')->set('stockPara', '2')->call('createHeader')
            ->call('handleProductSelected', 'transfer-detail', ['id' => 4])
            ->call('selectLotFromModal', 8);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('selectedLots.8.costo', 0);
    }

    public function test_it_prints_fresh_api_data_and_only_shows_print_options_for_outgoing_transfers(): void
    {
        Http::fake([
            'api.example.test/api/farma/transferencias/buscar/42' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => ['id' => 42, 'estado' => 'enviada', 'puede_imprimir' => true,
                    'stock_de' => ['descripcion' => 'Origen'], 'stock_para' => ['descripcion' => 'Destino'],
                    'detalles' => [['cantidad' => 2, 'subtotal' => 20, 'isv' => 3, 'total' => 23,
                        'lote' => ['codigo' => 'L-42', 'producto' => ['descripcion' => 'Producto actualizado']]]]],
            ]),
            'api.example.test/api/farma/transferencias/buscar/43' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => ['id' => 43, 'estado' => 'enviada', 'puede_imprimir' => false],
            ]),
        ]);
        $component = Livewire::test(Transferencias::class)
            ->assertSee('Térmica (80 mm)')->assertSee('Papel A4')
            ->set('direction', 'recibidas')->assertDontSee('data-test="print-transfer"', false)
            ->call('openDetail', 42)->assertSee('data-test="print-transfer"', false);
        foreach (['thermal', 'a4'] as $format) {
            $component->call('printTransfer', 42, $format)
                ->assertDispatched('transfer:print', fn (string $name, array $params): bool => str_contains($params['html'], 'Producto actualizado'));
        }
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/buscar/42') && $request->hasHeader('Action-Btn', 'trfbtn06'));
        $component->call('openDetail', 43)->assertDontSee('data-test="print-transfer"', false)
            ->call('printTransfer', 43, 'thermal')->assertNotDispatched('transfer:print')->assertDispatched('sweetalert:alert')
            ->call('printTransfer', 42, 'invalid')->assertNotDispatched('transfer:print');
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }
}
