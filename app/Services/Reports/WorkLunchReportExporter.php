<?php

namespace App\Services\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class WorkLunchReportExporter
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $criteria
     */
    public function excel(array $rows, array $criteria): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator(config('app.name', 'ShAdmin'))
            ->setTitle('Reporte de jornada laboral')
            ->setSubject('Entradas, salidas y tiempos de almuerzo');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jornada laboral');
        $sheet->setShowGridlines(false);
        $sheet->mergeCells('A1:M1');
        $sheet->setCellValue('A1', 'REPORTE DE JORNADA LABORAL');
        $sheet->mergeCells('A2:M2');
        $sheet->setCellValue('A2', 'Generado: '.now()->format('Y-m-d H:i'));
        $sheet->mergeCells('A3:M3');
        $sheet->setCellValue('A3', 'Filtros: '.($criteria === [] ? 'Ninguno' : implode(' | ', $criteria)));

        $headers = [
            'Fecha',
            'Trabajador',
            'Ubicación',
            'Dispositivo',
            'Entrada',
            'Salida',
            'Jornada neta',
            'Jornada esperada',
            'Estado jornada',
            'Inicio almuerzo',
            'Fin almuerzo',
            'Almuerzo',
            'Estado almuerzo',
        ];
        $sheet->fromArray($headers, null, 'A5');

        foreach ($rows as $index => $row) {
            $excelRow = $index + 6;
            $this->setDate($sheet, "A{$excelRow}", $row['date_iso'] ?? null, 'yyyy-mm-dd');
            $sheet->setCellValue("B{$excelRow}", (string) ($row['user'] ?? ''));
            $sheet->setCellValue("C{$excelRow}", (string) ($row['stock'] ?? ''));
            $sheet->setCellValue("D{$excelRow}", (string) ($row['device'] ?? ''));
            $this->setDate($sheet, "E{$excelRow}", $row['work_start_iso'] ?? null, 'h:mm AM/PM');
            $this->setDate($sheet, "F{$excelRow}", $row['work_end_iso'] ?? null, 'h:mm AM/PM');
            $this->setDuration($sheet, "G{$excelRow}", $row['net_work_minutes'] ?? null);
            $this->setDuration($sheet, "H{$excelRow}", $row['work_expected_minutes'] ?? null);
            $sheet->setCellValue("I{$excelRow}", (string) ($row['work_status_label'] ?? ''));
            $this->setDate($sheet, "J{$excelRow}", $row['lunch_start_iso'] ?? null, 'h:mm AM/PM');
            $this->setDate($sheet, "K{$excelRow}", $row['lunch_end_iso'] ?? null, 'h:mm AM/PM');
            $this->setDuration($sheet, "L{$excelRow}", $row['lunch_minutes'] ?? null);
            $sheet->setCellValue("M{$excelRow}", (string) ($row['lunch_status_label'] ?? ''));
        }

        $lastRow = max(6, count($rows) + 5);
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle('A2:M3')->applyFromArray([
            'font' => ['color' => ['rgb' => '475569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getStyle('A5:M5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A5:M{$lastRow}")->applyFromArray([
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A6:M{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("A6:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E6:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("J6:L{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach (range(6, $lastRow) as $rowNumber) {
            $sheet->getRowDimension($rowNumber)->setRowHeight(34);

            if ($rowNumber % 2 === 0) {
                $sheet->getStyle("A{$rowNumber}:M{$rowNumber}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('EFF6FF');
            }
        }

        foreach ($rows as $index => $row) {
            $excelRow = $index + 6;
            $this->applyStatusStyle($sheet, "I{$excelRow}", (string) ($row['work_status'] ?? ''));
            $this->applyStatusStyle($sheet, "M{$excelRow}", (string) ($row['lunch_status'] ?? ''));
        }

        foreach ([
            'A' => 13,
            'B' => 24,
            'C' => 25,
            'D' => 18,
            'E' => 15,
            'F' => 15,
            'G' => 15,
            'H' => 17,
            'I' => 25,
            'J' => 17,
            'K' => 17,
            'L' => 14,
            'M' => 25,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->freezePane('A6');
        $sheet->setAutoFilter("A5:M{$lastRow}");
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A3)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()
            ->setTop(0.5)
            ->setRight(0.35)
            ->setBottom(0.5)
            ->setLeft(0.35);
        $sheet->getHeaderFooter()
            ->setOddFooter('&L'.config('app.name', 'ShAdmin').'&RPágina &P de &N');

        ob_start();

        try {
            (new Xlsx($spreadsheet))->save('php://output');
            $content = ob_get_clean();
        } finally {
            $spreadsheet->disconnectWorksheets();

            if (ob_get_level() > 0 && ! isset($content)) {
                ob_end_clean();
            }
        }

        if (! is_string($content)) {
            throw new RuntimeException('No fue posible generar el archivo Excel.');
        }

        return $content;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $criteria
     */
    public function pdf(array $rows, array $criteria): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('reportes.exports.jornada-laboral-pdf', [
            'rows' => $rows,
            'criteria' => $criteria,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->render(), 'UTF-8');
        $pdf->setPaper('a4', 'landscape');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text(
            690,
            570,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $font,
            8,
            [0.28, 0.33, 0.41],
        );

        return $pdf->output();
    }

    private function setDate(
        Worksheet $sheet,
        string $cell,
        mixed $value,
        string $format,
    ): void {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        $sheet->setCellValue($cell, ExcelDate::PHPToExcel(Carbon::parse($value)));
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode($format);
    }

    private function setDuration(
        Worksheet $sheet,
        string $cell,
        mixed $minutes,
    ): void {
        if (! is_numeric($minutes)) {
            return;
        }

        $sheet->setCellValue($cell, max(0, (int) $minutes) / 1440);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('[h]:mm');
    }

    private function applyStatusStyle(
        Worksheet $sheet,
        string $cell,
        string $status,
    ): void {
        [$background, $foreground] = match ($status) {
            'short' => ['FEE2E2', '991B1B'],
            'exceeded' => ['FEF3C7', '92400E'],
            'compliant' => ['DCFCE7', '166534'],
            'pending' => ['DBEAFE', '1E40AF'],
            default => ['E2E8F0', '475569'],
        };

        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $foreground]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $background]],
        ]);
    }
}
