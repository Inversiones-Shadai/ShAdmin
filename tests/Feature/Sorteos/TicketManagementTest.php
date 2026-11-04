<?php

namespace Tests\Feature\Sorteos;

use App\Livewire\Sorteos\Tickets;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TicketManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.ticket_path' => '/sorteos/ticket',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        $this->actingAs(User::factory()->create());
        session()->put('auth.api', [
            'token' => 'api-token',
            'uname' => 'Administrador',
            'urol' => 'ADMIN',
            'stockid' => 7,
            'stockname' => 'Principal',
            'spermisos' => $this->allTicketPermissions(),
        ]);
        Http::preventStrayRequests();
    }

    public function test_component_lists_tickets_and_exposes_the_active_promotion(): void
    {
        $this->fakeTicketFlow();

        Livewire::test(Tickets::class)
            ->assertSee('#1200')
            ->assertSee('Ana María López Díaz')
            ->assertSee('Sorteo activo')
            ->assertSee('Principal')
            ->assertSet('total', 21)
            ->assertSet('activePromotion.id', 13)
            ->assertSee('data-test="generate-ticket-button"', false);
    }

    public function test_existing_client_must_update_phone_before_ticket_is_created_and_printed(): void
    {
        $this->fakeTicketFlow(phoneActive: false);

        Livewire::test(Tickets::class)
            ->call('openGenerator')
            ->assertSet('showTicketModal', true)
            ->assertSet('copies', 2)
            ->call('selectClient', 8)
            ->assertSet('phoneStatus', 'required')
            ->set('phone', '88887777')
            ->call('updatePhone')
            ->assertSet('phoneStatus', 'active')
            ->call('generateTicket')
            ->assertHasNoErrors()
            ->assertSet('showTicketModal', false)
            ->assertSet('lastTicketData.id', 99)
            ->assertSet('lastTicketData.numero', 1200)
            ->assertDispatched('ticket:print')
            ->assertDispatched('sweetalert:toast')
            ->call('reprintLastTicket')
            ->assertDispatched('ticket:print');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/sorteos/ticket/updatephone'
            && $request->data() === ['id' => 8, 'telefono' => '88887777']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/sorteos/ticket/create'
            && $request->data()['promocion'] === ['id' => 13]
            && $request->data()['cliente'] === ['id' => 8]
            && $request->data()['stock'] === ['id' => 7]);
    }

    public function test_new_client_loads_locations_and_is_selected_with_an_active_phone(): void
    {
        $this->fakeTicketFlow();

        Livewire::test(Tickets::class)
            ->call('openGenerator')
            ->call('openClientForm')
            ->assertSet('showClientModal', true)
            ->assertSet('departments', [])
            ->set('newDepartment', '1')
            ->assertSet('municipalities', [])
            ->set('newDocument', '0801199912345')
            ->set('newFirstName', 'Ana')
            ->set('newSecondName', 'María')
            ->set('newFirstSurname', 'López')
            ->set('newSecondSurname', 'Díaz')
            ->set('newAge', '27')
            ->set('newPhone', '99998888')
            ->set('newGender', 'FEMENINO')
            ->set('newMunicipality', '10')
            ->call('createClient')
            ->assertHasNoErrors()
            ->assertSet('showClientModal', false)
            ->assertSet('selectedClientId', 8)
            ->assertSet('phoneStatus', 'active')
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/sorteos/ticket/creacliente'
            && $request->data()['departamento'] === ['id' => 1]
            && $request->data()['municipio'] === ['id' => 10]
            && $request->data()['telefono'] === '99998888');
    }

    public function test_client_picker_searches_while_typing_and_discards_short_query_results(): void
    {
        $this->fakeTicketFlow();
        Http::fake([
            'api.example.test/api/sorteos/ticket/buscarcliente*' => Http::response([
                'code' => 200, 'data' => [$this->clientData()], 'message' => 'ok',
            ]),
        ]);

        Livewire::test(Tickets::class)
            ->call('openGenerator')
            ->set('clientSearch', 'Ana')
            ->assertHasNoErrors('clientSearch')
            ->assertSet('clients.0.id', 8)
            ->set('clientSearch', 'A')
            ->assertSet('clients', [])
            ->assertHasNoErrors('clientSearch');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/selectors/ticket-client-search?')
            && $request['search'] === 'Ana' && $request['page'] === 1);
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/selectors/ticket-client-search?')));
    }

    public function test_route_menu_and_ticket_icon_are_available_with_list_permission(): void
    {
        $this->fakeTicketFlow();

        $this->get(route('sorteos.tickets'))
            ->assertOk()
            ->assertSee('Generación de tickets')
            ->assertSee('data:image/png;base64,', false);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="permission-view-srts-tks"', false)
            ->assertSee('Generación de tickets');
    }

    public function test_client_pages_keep_the_chosen_client_and_only_request_the_current_page(): void
    {
        Http::swap(new Factory);
        Http::fake([
            '*/sorteos/ticket/all*' => Http::response(['code' => 200, 'data' => [$this->ticketData()]]),
            '*/sorteos/ticket/sorteoactual' => Http::response(['code' => 200, 'data' => $this->promotionData()]),
            '*/sorteos/ticket/activephone/8' => Http::response(['code' => 200, 'data' => true]),
            '*/selectors/ticket-clients*' => function (Request $request) {
                $page = (int) $request['page'];
                $record = [...$this->clientData(), 'id' => $page === 1 ? 8 : 9];

                return Http::response(['code' => 200, 'data' => [['id' => (string) $record['id'], 'label' => 'Cliente', 'record' => $record]],
                    'meta' => ['current_page' => $page, 'last_page' => 2, 'total' => 11]]);
            },
        ]);
        Livewire::test(Tickets::class)->call('openGenerator')->call('selectClient', 8)
            ->assertSet('selectedClientId', 8)->call('changeClientPage', 1)
            ->assertSet('clientPage', 2)->assertSet('clients.0.id', 9)
            ->assertSet('selectedClientId', 8)->assertSet('selectedClientName', 'Ana María López Díaz');
        $requests = Http::recorded(fn (Request $request) => str_contains($request->url(), '/selectors/ticket-clients?'));
        $this->assertCount(2, $requests);
        $this->assertSame(2, $requests->last()[0]['page']);
    }

    private function fakeTicketFlow(bool $phoneActive = true): void
    {
        Http::fake([
            'api.example.test/api/sorteos/ticket/all*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [$this->ticketData()],
                'meta' => ['total' => 21, 'current_page' => 1, 'last_page' => 2],
            ]),
            'api.example.test/api/sorteos/ticket/sorteoactual' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => $this->promotionData(),
            ]),
            'api.example.test/api/sorteos/ticket/clientes*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [$this->clientData()],
            ]),
            'api.example.test/api/sorteos/ticket/activephone/8' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => $phoneActive,
            ]),
            'api.example.test/api/sorteos/ticket/updatephone' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/sorteos/ticket/departamentos' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [['id' => 1, 'nombre' => 'Francisco Morazán']],
            ]),
            'api.example.test/api/sorteos/ticket/municipios/1' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [['id' => 10, 'nombre' => 'Distrito Central']],
            ]),
            'api.example.test/api/sorteos/ticket/creacliente' => Http::response([
                'message' => 'Cliente creado correctamente',
                'code' => 200,
                'data' => $this->clientData(),
            ]),
            'api.example.test/api/sorteos/ticket/create' => Http::response([
                'message' => 'Ticket created',
                'code' => 200,
                'data' => $this->printTicketData(),
            ]),
        ]);
        $this->fakeSelectorsFromLegacy(['ticket-clients' => 'sorteos/ticket/clientes', 'ticket-client-search' => 'sorteos/ticket/buscarcliente']);
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketData(): array
    {
        return [
            'id' => 99,
            'ntiket' => 1200,
            'promocion' => $this->promotionData(),
            'cliente' => $this->clientData(),
            'usuario' => ['id' => 2, 'nombre' => 'Administrador'],
            'stock' => ['id' => 7, 'descripcion' => 'Principal'],
            'created_at' => '2026-07-27T20:30:00.000000Z',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function printTicketData(): array
    {
        return [
            'id' => 99,
            'numero' => 1200,
            'valor' => 50,
            'fecha' => '2026-07-27T20:30:00-06:00',
            'template_version' => 1,
            'promocion' => [
                'id' => 13,
                'nombre' => 'Sorteo activo',
            ],
            'cliente' => [
                'id' => 8,
                'nombre' => 'Ana López',
                'identidad' => '0801199912345',
                'telefono' => '99998888',
            ],
            'stock' => [
                'id' => 7,
                'descripcion' => 'Principal',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function promotionData(): array
    {
        return [
            'id' => 13,
            'nombre' => 'Sorteo activo',
            'fecha_fin' => '2026-08-30',
            'valor' => 50,
            'impresiones' => 2,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clientData(): array
    {
        return [
            'id' => 8,
            'docid' => '0801199912345',
            'pnombre' => 'Ana',
            'snombre' => 'María',
            'papellido' => 'López',
            'spaellido' => 'Díaz',
            'telefono' => '99998888',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allTicketPermissions(): array
    {
        return [[
            'modulo_l' => ['codigo' => 'srts'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'tks'],
                'accionesi' => collect([
                    'tksbnt01',
                    'tksbnt02',
                    'tksbnt03',
                    'tksbnt04',
                    'tksbnt05',
                    'tksbnt06',
                    'tksbtn07',
                    'tksbnt08',
                    'tksbnt09',
                    'tksbtn10',
                    'tksbnt11',
                ])->map(fn (string $code): array => ['codigo' => $code])->all(),
            ]],
        ]];
    }
}
