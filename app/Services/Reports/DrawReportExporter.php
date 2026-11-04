<?php

namespace App\Services\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class DrawReportExporter
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
            ->setTitle('Reporte de sorteos')
            ->setSubject('Tickets filtrados del reporte de sorteos');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte sorteos');
        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'REPORTE DE SORTEOS');
        $sheet->mergeCells('A2:G2');
        $sheet->setCellValue('A2', 'Generado: '.now()->format('Y-m-d H:i'));
        $sheet->mergeCells('A3:G3');
        $sheet->setCellValue('A3', 'Filtros: '.($criteria === [] ? 'Ninguno' : implode(' | ', $criteria)));

        $headers = ['Ticket', 'Sorteo', 'Cliente', 'Identidad', 'Usuario', 'Stock', 'Fecha'];
        $sheet->fromArray($headers, null, 'A5');

        foreach ($rows as $index => $row) {
            $excelRow = $index + 6;
            $sheet->setCellValueExplicit("A{$excelRow}", (string) ($row['number'] ?? ''), DataType::TYPE_INLINE);
            $sheet->setCellValue("B{$excelRow}", (string) ($row['promotion'] ?? ''));
            $sheet->setCellValue("C{$excelRow}", (string) ($row['client'] ?? ''));
            $sheet->setCellValueExplicit("D{$excelRow}", (string) ($row['document'] ?? ''), DataType::TYPE_INLINE);
            $sheet->setCellValue("E{$excelRow}", (string) ($row['user'] ?? ''));
            $sheet->setCellValue("F{$excelRow}", (string) ($row['stock'] ?? ''));

            $date = trim((string) ($row['created_at'] ?? ''));

            if ($date !== '') {
                $sheet->setCellValue("G{$excelRow}", Carbon::parse($date));
                $sheet->getStyle("G{$excelRow}")
                    ->getNumberFormat()
                    ->setFormatCode('yyyy-mm-dd hh:mm');
            }
        }

        $lastRow = max(6, count($rows) + 5);
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle('A2:G3')->applyFromArray([
            'font' => ['color' => ['rgb' => '475569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getStyle('A5:G5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A5:G{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A6:A{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle("A6:A{$lastRow}")
            ->setQuotePrefix(true);
        $sheet->getStyle("A6:A{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("D6:D{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle("D6:D{$lastRow}")
            ->setQuotePrefix(true);
        $sheet->getStyle("D6:D{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("B6:F{$lastRow}")
            ->getAlignment()
            ->setWrapText(true);

        foreach (range(6, $lastRow) as $rowNumber) {
            $sheet->getRowDimension($rowNumber)->setRowHeight(34);

            if ($rowNumber % 2 === 0) {
                $sheet->getStyle("A{$rowNumber}:G{$rowNumber}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('EFF6FF');
            }
        }

        foreach ([
            'A' => 14,
            'B' => 32,
            'C' => 36,
            'D' => 22,
            'E' => 24,
            'F' => 24,
            'G' => 20,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->freezePane('A6');
        $sheet->setAutoFilter("A5:G{$lastRow}");
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
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
        $pdf->loadHtml(view('reportes.exports.sorteos-pdf', [
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
}
