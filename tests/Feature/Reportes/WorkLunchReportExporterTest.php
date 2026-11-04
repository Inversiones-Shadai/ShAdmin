<?php

namespace Tests\Feature\Reportes;

use App\Services\Reports\WorkLunchReportExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class WorkLunchReportExporterTest extends TestCase
{
    public function test_it_generates_a_valid_pdf_with_work_and_lunch_compliance(): void
    {
        $content = app(WorkLunchReportExporter::class)->pdf(
            $this->rows(),
            ['Trabajador: Ana López', 'Jornada esperada: 08:00'],
        );

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(1000, strlen($content));
    }

    public function test_it_generates_a_formatted_excel_with_typed_dates_and_durations(): void
    {
        $content = app(WorkLunchReportExporter::class)->excel(
            $this->rows(),
            ['Trabajador: Ana López', 'Jornada esperada: 08:00'],
        );
        $path = tempnam(sys_get_temp_dir(), 'work-lunch-report-');

        try {
            file_put_contents($path, $content);
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame('REPORTE DE JORNADA LABORAL', $sheet->getCell('A1')->getValue());
            $this->assertSame('Fecha', $sheet->getCell('A5')->getValue());
            $this->assertSame('Jornada neta', $sheet->getCell('G5')->getValue());
            $this->assertSame('2026-07-30', $sheet->getCell('A6')->getFormattedValue());
            $this->assertSame('7:30', $sheet->getCell('G6')->getFormattedValue());
            $this->assertSame('[h]:mm', $sheet->getStyle('G6')->getNumberFormat()->getFormatCode());
            $this->assertSame('Incompleta - faltaron 30 min', $sheet->getCell('I6')->getValue());
            $this->assertSame('Excedido por 15 min', $sheet->getCell('M6')->getValue());
            $this->assertSame('FEE2E2', $sheet->getStyle('I6')->getFill()->getStartColor()->getRGB());
            $this->assertSame('FEF3C7', $sheet->getStyle('M6')->getFill()->getStartColor()->getRGB());
            $this->assertSame('A6', $sheet->getFreezePane());
            $this->assertSame('A5:M6', $sheet->getAutoFilter()->getRange());
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
            'id' => 41,
            'date' => '30/07/2026',
            'date_iso' => '2026-07-30',
            'user' => 'Ana López',
            'stock' => 'Sucursal Centro',
            'device' => 'CAJA-01',
            'work_start' => '8:00 AM',
            'work_start_iso' => '2026-07-30 08:00:00',
            'work_end' => '4:15 PM',
            'work_end_iso' => '2026-07-30 16:15:00',
            'net_work_minutes' => 450,
            'net_work_duration' => '07:30',
            'work_expected_minutes' => 480,
            'work_expected_duration' => '08:00',
            'work_status' => 'short',
            'work_status_label' => 'Incompleta - faltaron 30 min',
            'lunch_start' => '12:00 PM',
            'lunch_start_iso' => '2026-07-30 12:00:00',
            'lunch_end' => '12:45 PM',
            'lunch_end_iso' => '2026-07-30 12:45:00',
            'lunch_minutes' => 45,
            'lunch_duration' => '00:45',
            'lunch_expected_minutes' => 30,
            'lunch_expected_duration' => '00:30',
            'lunch_status' => 'exceeded',
            'lunch_status_label' => 'Excedido por 15 min',
        ]];
    }
}
