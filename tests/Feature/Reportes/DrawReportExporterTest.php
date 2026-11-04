<?php

namespace Tests\Feature\Reportes;

use App\Services\Reports\DrawReportExporter;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DrawReportExporterTest extends TestCase
{
    public function test_it_generates_a_valid_pdf_with_report_content(): void
    {
        $content = app(DrawReportExporter::class)->pdf(
            $this->rows(),
            ['Sorteo: Sorteo de verano'],
        );

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(1000, strlen($content));
    }

    public function test_it_generates_a_formatted_excel_workbook(): void
    {
        $content = app(DrawReportExporter::class)->excel(
            $this->rows(),
            ['Sorteo: Sorteo de verano'],
        );
        $path = tempnam(sys_get_temp_dir(), 'draw-report-');

        try {
            file_put_contents($path, $content);
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame('REPORTE DE SORTEOS', $sheet->getCell('A1')->getValue());
            $this->assertSame('Ticket', $sheet->getCell('A5')->getValue());
            $this->assertSame('1024', $sheet->getCell('A6')->getFormattedValue());
            $this->assertSame(DataType::TYPE_INLINE, $sheet->getCell('A6')->getDataType());
            $this->assertSame('Sorteo de verano', $sheet->getCell('B6')->getValue());
            $this->assertSame('0801199012345', $sheet->getCell('D6')->getFormattedValue());
            $this->assertSame(DataType::TYPE_INLINE, $sheet->getCell('D6')->getDataType());
            $this->assertTrue($sheet->getStyle('D6')->getQuotePrefix());
            $this->assertSame('A6', $sheet->getFreezePane());
            $this->assertSame('A5:G6', $sheet->getAutoFilter()->getRange());
            $spreadsheet->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(): array
    {
        return [[
            'id' => 15,
            'number' => '1024',
            'promotion' => 'Sorteo de verano',
            'client' => 'Ana María López Díaz',
            'document' => '0801199012345',
            'user' => 'Operador',
            'stock' => 'Sucursal Centro',
            'created_at' => '2026-07-29 10:30',
        ]];
    }
}
