<?php

namespace Tests\Feature\Farma;

use App\Services\Printing\TransferPrinter;
use InvalidArgumentException;
use Tests\TestCase;

class TransferPrinterTest extends TestCase
{
    public function test_both_formats_use_saved_amounts_and_include_the_sender_information(): void
    {
        foreach (['thermal', 'a4'] as $format) {
            $html = app(TransferPrinter::class)->render($this->transfer(), $format);
            foreach (['560509', 'Principal', 'Sucursal Centro', 'Ana López', '30/09/2026 10:31:54', 'VASO PARA MUESTRA', '00V52477', 'L 80.00', 'L 12.00', 'L 92.00', '4.00', 'data:image/png;base64,'] as $text) {
                $this->assertStringContainsString($text, $html);
            }
            $this->assertStringNotContainsString('999.00', $html);
        }
    }

    public function test_a4_contains_only_the_requested_product_fields_in_one_row(): void
    {
        $html = app(TransferPrinter::class)->render($this->transfer(), 'a4');
        foreach (['7701234567890', 'Material médico', '2026-01-01', '2028-01-01', 'LOT-2026', 'Barrio Centro', '9999-8888', 'Firma de quien envía'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['Laboratorio Uno', 'Frasco', 'Diagnóstico', 'Uso externo', 'UND (1)', 'FAC-100', 'ID producto / lote', 'ISV aplicado'] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $rows = $xpath->query('//table[@class="products"]/tbody/tr');
        $this->assertCount(1, $rows);
        $cells = $xpath->query('./td', $rows->item(0));
        $this->assertSame([
            '00V52477', '7701234567890', 'VASO PARA MUESTRA DE ORINA', 'LOT-2026',
            '2026-01-01', '2028-01-01', 'Material médico', '20', '4.00', '12.00', '80.00',
        ], array_map(fn ($cell) => trim($cell->textContent), iterator_to_array($cells)));
        $this->assertStringContainsString('size: A4 portrait', $html);
    }

    public function test_print_data_is_escaped_and_receipt_details_stay_out_of_the_basic_format(): void
    {
        $transfer = $this->transfer();
        $transfer['detalles'][0]['lote']['producto']['descripcion'] = '<script>alert(1)</script>';
        foreach (['thermal', 'a4'] as $format) {
            $html = app(TransferPrinter::class)->render($transfer, $format);
            $this->assertStringNotContainsString('<script>', $html);
            $this->assertStringContainsString('&lt;script&gt;', $html);
        }
        $html = app(TransferPrinter::class)->render($transfer, 'thermal');
        $this->assertStringNotContainsString('Laboratorio Uno', $html);
        $this->assertStringContainsString('size: 80mm', $html);
    }

    public function test_incoming_unsent_or_empty_transfers_and_unknown_formats_are_rejected(): void
    {
        foreach ([
            [array_replace($this->transfer(), ['puede_imprimir' => false]), 'a4'],
            [array_replace($this->transfer(), ['estado_label' => 'PENDIENTE']), 'thermal'],
            [array_replace($this->transfer(), ['detalles' => []]), 'a4'],
            [$this->transfer(), '../ticket'],
        ] as [$transfer, $format]) {
            try {
                app(TransferPrinter::class)->render($transfer, $format);
                $this->fail('Se permitió imprimir una transferencia o un formato no autorizado.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    /** @return array<string, mixed> */
    private function transfer(): array
    {
        return [
            'id' => 560509, 'estado_label' => 'ENVIADA', 'puede_imprimir' => true,
            'tipo' => ['descripcion' => 'TRANS F3'], 'estado_recepcion_label' => 'PENDIENTE',
            'enviado_at' => '2026-09-30T16:31:54+00:00', 'recibido_at' => null,
            'stock_de' => ['id' => 1, 'descripcion' => 'Principal', 'ubicacion' => 'Barrio Centro', 'telefono' => '9999-8888'],
            'stock_para' => ['id' => 2, 'descripcion' => 'Sucursal Centro'],
            'usuario_envia' => ['nombre' => 'Ana López'], 'usuario_recibe' => null,
            'subtotal' => 80, 'isv' => 12, 'total' => 92,
            'detalles' => [[
                'cantidad' => 20, 'subtotal' => 80, 'isv' => 12, 'total' => 92,
                'lote' => [
                    'id' => 10, 'codigo' => 'LOT-2026', 'costo' => 999,
                    'fecha_elaboracion' => '2026-01-01', 'fecha_expiracion' => '2028-01-01', 'factura' => 'FAC-100',
                    'producto' => [
                        'id' => 2, 'codigo' => '00V52477', 'codigobar' => '7701234567890',
                        'descripcion' => 'VASO PARA MUESTRA DE ORINA', 'laboratorio' => 'Laboratorio Uno',
                        'categoria' => 'Material médico', 'familia' => 'Diagnóstico',
                        'presentacion' => 'Frasco', 'administracion' => 'Uso externo', 'unidad' => 'UND (1)',
                    ],
                ],
            ]],
        ];
    }
}
