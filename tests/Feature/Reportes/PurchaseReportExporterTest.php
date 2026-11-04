<?php

namespace Tests\Feature\Reportes;

use App\Services\Reports\PurchaseReportExporter;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PurchaseReportExporterTest extends TestCase
{
    public function test_pdf_contains_invoice_sections_payments_and_final_totals(): void
    {
        $rows = require base_path('tests/Fixtures/purchase-report-export.php');
        $exporter = app(PurchaseReportExporter::class);
        $data = $exporter->reportData($rows);
        $this->assertSame(['subtotal' => 350.0, 'isv' => 15.0, 'descuento' => 5.0, 'total' => 360.0, 'abonos' => 20.0, 'aplicado' => 225.0, 'saldo' => 135.0], $data['totals']);
        $html = view('reportes.exports.compras-pdf', [...$data, 'criteria' => ['Tipo: Todos'], 'generatedAt' => '17/10/2026 10:00'])->render();
        foreach (['F-001', 'F-002', 'F-003', 'AB-001', 'NC-001', 'Sin pagos registrados.', 'Grandes totales', '360.00', '225.00', '135.00'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringStartsWith('%PDF-', $exporter->pdf($rows, ['Tipo: Todos']));
    }

    public function test_excel_has_every_payment_safe_text_and_working_invoice_and_grand_totals(): void
    {
        $rows = require base_path('tests/Fixtures/purchase-report-export.php');
        $path = tempnam(sys_get_temp_dir(), 'purchase-export-');
        try {
            file_put_contents($path, app(PurchaseReportExporter::class)->excel($rows, ['Tipo: Todos']));
            $book = IOFactory::load($path);
            $sheet = $book->getActiveSheet();
            $summaryRows = [];
            $grandRow = 0;
            $abonoCell = '';
            foreach ($sheet->getRowIterator() as $row) {
                $number = $row->getRowIndex();
                $value = $sheet->getCell('A'.$number)->getValue();
                if ($value === 'Compra') {
                    $summaryRows[] = $number;
                }
                if ($value === 'Totales generales') {
                    $grandRow = $number;
                }
                if ($sheet->getCell('E'.$number)->getValue() === 'AB-001') {
                    $abonoCell = 'G'.$number;
                }
                if ($sheet->getCell('E'.$number)->getValue() === '=1+1') {
                    $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('E'.$number)->getDataType());
                }
            }
            $this->assertCount(3, $summaryRows);
            $this->assertGreaterThan(0, $grandRow);
            foreach (['B' => 350, 'C' => 15, 'D' => 5, 'E' => 360, 'F' => 20, 'G' => 225, 'H' => 135] as $column => $value) {
                $this->assertEquals($value, $sheet->getCell($column.$grandRow)->getCalculatedValue());
            }
            $this->assertEquals(25, $sheet->getCell('G'.$summaryRows[0])->getCalculatedValue());
            $this->assertEquals(85, $sheet->getCell('H'.$summaryRows[0])->getCalculatedValue());
            $this->assertNotSame('', $abonoCell);
            $sheet->setCellValue($abonoCell, 30);
            $book->getCalculationEngine()->clearCalculationCache();
            $this->assertEquals(235, $sheet->getCell('G'.$grandRow)->getCalculatedValue());
            $this->assertEquals(125, $sheet->getCell('H'.$grandRow)->getCalculatedValue());
            $book->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }
}
