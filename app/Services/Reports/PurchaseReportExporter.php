<?php

namespace App\Services\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class PurchaseReportExporter
{
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
        $pdf->loadHtml(view('reportes.exports.compras-pdf', [
            ...$this->reportData($rows), 'criteria' => $criteria,
            'generatedAt' => now()->timezone('America/Tegucigalpa')->format('d/m/Y H:i'),
        ])->render(), 'UTF-8');
        $pdf->setPaper('a4', 'portrait');
        $pdf->render();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $pdf->getCanvas()->page_text(425, 813, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 8, [0.35, 0.40, 0.48]);

        return $pdf->output();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $criteria
     */
    public function excel(array $rows, array $criteria): string
    {
        $data = $this->reportData($rows);
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('Shadmin')->setTitle('Reporte de compras con pagos');
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Compras y pagos');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet->setShowGridlines(false);
        $this->mergedText($sheet, 'A1:H1', 'REPORTE DE COMPRAS');
        $this->mergedText($sheet, 'A2:H2', 'Generado: '.now()->timezone('America/Tegucigalpa')->format('d/m/Y H:i').' | Moneda: HNL (L)');
        $this->mergedText($sheet, 'A3:H3', implode(' | ', $criteria));
        $sheet->getStyle('A1:H1')->getFont()->setSize(18)->setBold(true);
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getRowDimension(3)->setRowHeight(38);
        $sheet->getStyle('A3:H3')->getAlignment()->setWrapText(true);
        $row = 5;
        foreach ($data['purchases'] as $purchase) {
            $this->mergedText($sheet, "A{$row}:H{$row}", 'FACTURA '.$purchase['nro'].' | '.$purchase['estado_label']);
            $this->band($sheet, "A{$row}:H{$row}");
            $sheet->getRowDimension($row)->setRowHeight(26);
            $row++;
            $this->mergedText($sheet, "A{$row}:H{$row}", 'Proveedor: '.$purchase['supplier']);
            $sheet->getStyle("A{$row}:H{$row}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($row)->setRowHeight(34);
            $row++;
            $this->mergedText($sheet, "A{$row}:H{$row}", $purchase['type_label'].' | Plazo: '.$purchase['plazo'].' días | Fecha: '.$purchase['date']);
            $row++;
            $this->financialHeaders($sheet, $row++);
            $summaryRow = $row++;
            $this->text($sheet, 'A'.$summaryRow, 'Compra');
            foreach (['B' => 'subtotal', 'C' => 'isv', 'D' => 'descuento', 'E' => 'total', 'F' => 'total_abonos'] as $column => $key) {
                $sheet->setCellValue($column.$summaryRow, $purchase[$key]);
            }
            $row++;
            foreach (['A' => ['B', 'Fecha'], 'C' => ['D', 'Tipo de pago'], 'E' => ['F', 'Comprobante'], 'G' => ['H', 'Valor (L)']] as $column => [$end, $label]) {
                $this->mergedText($sheet, "{$column}{$row}:{$end}{$row}", $label);
            }
            $this->band($sheet, "A{$row}:H{$row}", 'E8EDF5', '24354C');
            $firstPayment = ++$row;
            foreach ($purchase['pagos'] as $payment) {
                $this->mergedText($sheet, "A{$row}:B{$row}", $payment['date']);
                $this->mergedText($sheet, "C{$row}:D{$row}", $payment['type_label']);
                $this->mergedText($sheet, "E{$row}:F{$row}", $payment['nro']);
                $sheet->mergeCells("G{$row}:H{$row}");
                $sheet->setCellValue('G'.$row, $payment['valor']);
                $sheet->getStyle("G{$row}:H{$row}")->getNumberFormat()->setFormatCode('"L "#,##0.00');
                $sheet->getStyle("A{$row}:F{$row}")->getAlignment()->setWrapText(true);
                $sheet->getRowDimension($row)->setRowHeight(30);
                $row++;
            }
            $lastPayment = $row - 1;
            if ($purchase['pagos'] === []) {
                $this->mergedText($sheet, "A{$row}:H{$row}", 'Sin pagos registrados.');
                $row++;
            }
            $sheet->setCellValue('G'.$summaryRow, $purchase['pagos'] === [] ? 0 : "=SUM(G{$firstPayment}:G{$lastPayment})");
            $sheet->setCellValue('H'.$summaryRow, "=MAX(0,E{$summaryRow}-G{$summaryRow})");
            $sheet->getStyle("B{$summaryRow}:H{$summaryRow}")->getNumberFormat()->setFormatCode('"L "#,##0.00');
            $this->mergedText($sheet, "A{$row}:F{$row}", 'Total de pagos aplicados a esta compra');
            $sheet->mergeCells("G{$row}:H{$row}");
            $sheet->setCellValue('G'.$row, '=G'.$summaryRow);
            $sheet->getStyle("G{$row}:H{$row}")->getNumberFormat()->setFormatCode('"L "#,##0.00');
            $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
            $row += 3;
        }
        $lastDetail = $row - 1;
        $this->mergedText($sheet, "A{$row}:H{$row}", 'GRANDES TOTALES | '.count($data['purchases']).' compras');
        $this->band($sheet, "A{$row}:H{$row}");
        $row++;
        $this->financialHeaders($sheet, $row++);
        $this->text($sheet, 'A'.$row, 'Totales generales');
        foreach (range('B', 'H') as $column) {
            $sheet->setCellValue($column.$row, '=SUMIF($A$5:$A$'.$lastDetail.',"Compra",'.$column.'$5:'.$column.'$'.$lastDetail.')');
        }
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
        $sheet->getStyle("B{$row}:H{$row}")->getNumberFormat()->setFormatCode('"L "#,##0.00');
        $sheet->getStyle("A1:H{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(25);
        foreach (range('B', 'H') as $column) {
            $sheet->getColumnDimension($column)->setWidth(17);
        }
        $sheet->freezePane('A5');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0)->setPrintArea('A1:H'.$row);
        $sheet->getHeaderFooter()->setOddFooter('&LShadmin - Compras y pagos&RPágina &P de &N');
        $level = ob_get_level();
        ob_start();
        try {
            (new Xlsx($book))->save('php://output');
            $content = ob_get_clean();
        } finally {
            $book->disconnectWorksheets();
            while (ob_get_level() > $level) {
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
     * @return array{purchases: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function reportData(array $rows): array
    {
        $purchases = [];
        $totals = array_fill_keys(['subtotal', 'isv', 'descuento', 'total', 'abonos', 'aplicado', 'saldo'], 0);
        foreach ($rows as $row) {
            $payments = [];
            $applied = $abonos = 0;
            foreach ($row['pagos'] as $payment) {
                $value = (int) round((float) $payment['valor'] * 100);
                $applied += $value;
                if ((int) $payment['tipo_id'] === 2) {
                    $abonos += $value;
                }
                $payments[] = ['nro' => (string) ($payment['nro'] ?? '—'), 'valor' => $value / 100,
                    'date' => $this->date($payment['created_at'] ?? null), 'type_label' => (string) data_get($payment, 'tipo.descripcion', '—')];
            }
            $purchase = $row;
            foreach (['subtotal', 'isv', 'descuento', 'total'] as $key) {
                $cents = (int) round((float) ($row[$key] ?? 0) * 100);
                $totals[$key] += $cents;
                $purchase[$key] = $cents / 100;
            }
            $balance = max(0, (int) round((float) $purchase['total'] * 100) - $applied);
            $totals['abonos'] += $abonos;
            $totals['aplicado'] += $applied;
            $totals['saldo'] += $balance;
            $purchases[] = [...$purchase, 'pagos' => $payments, 'nro' => (string) ($row['nro'] ?? ''),
                'estado_label' => (string) ($row['estado_label'] ?? ''), 'plazo' => (int) ($row['plazo'] ?? 0),
                'supplier' => (string) data_get($row, 'proveedor.nombre', '—'), 'type_label' => (string) data_get($row, 'tipo.descripcion', '—'),
                'date' => $this->date($row['created_at'] ?? null), 'total_aplicado' => $applied / 100, 'total_abonos' => $abonos / 100, 'saldo' => $balance / 100];
        }

        return ['purchases' => $purchases, 'totals' => array_map(static fn (int $cents): float => $cents / 100, $totals)];
    }

    private function date(mixed $value): string
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value)->timezone('America/Tegucigalpa')->format('d/m/Y H:i') : '—';
    }

    private function text(Worksheet $sheet, string $cell, string $value): void
    {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
        $sheet->getStyle($cell)->setQuotePrefix(true);
    }

    private function mergedText(Worksheet $sheet, string $range, string $value): void
    {
        $sheet->mergeCells($range);
        $this->text($sheet, explode(':', $range)[0], $value);
    }

    private function band(Worksheet $sheet, string $range, string $background = '173653', string $foreground = 'FFFFFF'): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($background);
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB($foreground);
    }

    private function financialHeaders(Worksheet $sheet, int $row): void
    {
        $sheet->fromArray(['Concepto', 'Subtotal', 'ISV', 'Descuento', 'Total', 'Abonos', 'Aplicado', 'Saldo pendiente'], null, 'A'.$row);
        $this->band($sheet, "A{$row}:H{$row}", 'E8EDF5', '24354C');
        $sheet->getStyle("A{$row}:H{$row}")->getAlignment()->setWrapText(true);
        $sheet->getRowDimension($row)->setRowHeight(30);
    }
}
