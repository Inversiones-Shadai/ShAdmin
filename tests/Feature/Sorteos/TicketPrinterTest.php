<?php

namespace Tests\Feature\Sorteos;

use App\Data\Api\TicketPrintData;
use App\Services\Printing\TicketPrinter;
use Tests\TestCase;

class TicketPrinterTest extends TestCase
{
    public function test_it_renders_all_requested_copies_for_browser_printing(): void
    {
        $ticket = TicketPrintData::fromApi($this->ticketData());

        $html = app(TicketPrinter::class)->render($ticket, 3);
        $this->assertSame(3, substr_count($html, 'BOLETO# 1200'));
        $this->assertStringContainsString('Ana López', $html);
        $this->assertStringContainsString('0801-1999-12345', $html);
        $this->assertStringContainsString('data:image/jpeg;base64,', $html);
        $this->assertStringNotContainsString('<embed', $html);

    }

    public function test_it_rejects_an_unsupported_ticket_template_version(): void
    {
        $data = $this->ticketData();
        $data['template_version'] = 2;

        $this->expectExceptionMessage('versión del formato del ticket no es compatible');

        TicketPrintData::fromApi($data);
    }

    public function test_it_prints_the_flat_ticket_contract_from_the_current_api(): void
    {
        $ticket = TicketPrintData::fromApi([
            'id' => 99, 'numero' => 1200, 'valor' => 50, 'template_version' => 1,
            'fecha' => '2026-07-27T20:30:00-06:00',
            'promocion_id' => 13, 'promocion' => 'Sorteo activo',
            'cliente_id' => 8, 'cliente_n' => 'Ana López',
            'cliente_i' => '0801199912345', 'cliente_t' => '99998888',
            'stock_id' => 7, 'stock' => 'Principal',
        ]);
        $this->assertSame($this->ticketData(), $ticket->toArray());
        $html = app(TicketPrinter::class)->render($ticket);
        $this->assertStringContainsString('BOLETO# 1200', $html);
        $this->assertStringContainsString('Ana López', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketData(): array
    {
        return [
            'id' => 99,
            'numero' => 1200,
            'valor' => 50.0,
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
}
