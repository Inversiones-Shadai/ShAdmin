<?php

namespace Tests\Feature\Sorteos;

use App\Exceptions\ApiOperationException;
use App\Services\Api\TicketApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TicketApiServiceTest extends TestCase
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

        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_uses_all_ticket_and_client_query_routes(): void
    {
        Http::fake([
            'api.example.test/api/sorteos/ticket/all*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [$this->ticketData()],
                'meta' => ['total' => 21, 'current_page' => 2, 'last_page' => 2],
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
            'api.example.test/api/sorteos/ticket/buscarcliente*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [$this->clientData()],
            ]),
            'api.example.test/api/sorteos/ticket/activephone/8' => Http::response([
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
        ]);

        $service = app(TicketApiService::class);

        $this->assertSame(21, $service->list(2)->total);
        $this->assertSame('Sorteo activo', $service->currentPromotion()['nombre']);
        $this->assertSame(8, $service->clients()[0]['id']);
        $this->assertSame(8, $service->searchClients('Ana')[0]['id']);
        $this->assertTrue($service->isPhoneActive(8));
        $this->assertSame('Francisco Morazán', $service->departments()[0]['nombre']);
        $this->assertSame('Distrito Central', $service->municipalities(1)[0]['nombre']);

        Http::assertSent(fn (Request $request): bool => str_starts_with(
            $request->url(),
            'https://api.example.test/api/sorteos/ticket/buscarcliente?',
        )
            && $request->data()['filterItems'][0] === [
                'key' => 'docid',
                'value' => 'Ana',
                'operator' => 'LIKE_ALL',
                'logicalOperator' => 'OR',
            ]
            && $request->data()['filterItems'][2]['key'] === 'papellido');
    }

    public function test_it_creates_and_updates_clients_and_creates_a_printable_ticket(): void
    {
        Http::fake([
            'api.example.test/api/sorteos/ticket/creacliente' => Http::response([
                'message' => 'Cliente creado correctamente',
                'code' => 200,
                'data' => $this->clientData(),
            ]),
            'api.example.test/api/sorteos/ticket/updatephone' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/sorteos/ticket/create' => Http::response([
                'message' => 'Ticket created',
                'code' => 200,
                'data' => $this->printTicketData(),
            ]),
        ]);

        $service = app(TicketApiService::class);
        $clientPayload = [
            'docid' => '0801199912345',
            'pnombre' => 'Ana',
            'snombre' => 'María',
            'papellido' => 'López',
            'spaellido' => 'Díaz',
            'edad' => 27,
            'telefono' => '99998888',
            'genero' => 'FEMENINO',
            'departamento' => ['id' => 1],
            'municipio' => ['id' => 10],
        ];

        $this->assertSame(8, $service->createClient($clientPayload)['id']);
        $this->assertSame('ok', $service->updatePhone(8, '88887777'));
        $ticket = $service->createTicket(13, 8, 7);

        $this->assertSame(99, $ticket->id);
        $this->assertSame(1200, $ticket->number);
        $this->assertSame('Sorteo activo', $ticket->promotionName);
        $this->assertSame('Ana López', $ticket->clientName);
        $this->assertSame('Principal', $ticket->stockName);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/sorteos/ticket/creacliente'
            && $request->data() === $clientPayload);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/sorteos/ticket/updatephone'
            && $request->data() === ['id' => 8, 'telefono' => '88887777']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/sorteos/ticket/create'
            && $request->data() === [
                'promocion' => ['id' => 13],
                'cliente' => ['id' => 8],
                'stock' => ['id' => 7],
            ]);
    }

    public function test_it_rejects_an_invalid_print_document(): void
    {
        Http::fake([
            'api.example.test/api/sorteos/ticket/create' => Http::response([
                'message' => 'Ticket created',
                'code' => 200,
                'data' => [
                    'id' => 99,
                    'numero' => 1200,
                    'template_version' => 1,
                ],
            ]),
        ]);

        $this->expectException(ApiOperationException::class);
        $this->expectExceptionMessage('datos válidos para imprimir');

        app(TicketApiService::class)->createTicket(13, 8, 7);
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
}
