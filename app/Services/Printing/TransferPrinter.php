<?php

namespace App\Services\Printing;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;
use function in_array;

class TransferPrinter
{
    /** @param array<string, mixed> $transfer */
    public function render(array $transfer, string $format): string
    {
        if (! in_array($format, ['thermal', 'a4'], true)) {
            throw new InvalidArgumentException('Selecciona un formato de impresión válido.');
        }
        $status = mb_strtolower((string) ($transfer['estado_label'] ?? $transfer['estado'] ?? ''));
        if (($transfer['puede_imprimir'] ?? false) !== true || $status !== 'enviada') {
            throw new InvalidArgumentException('Solo puedes imprimir transferencias enviadas desde este stock.');
        }
        $details = $transfer['detalles'] ?? [];
        if (! is_array($details) || $details === []) {
            throw new InvalidArgumentException('La transferencia no tiene productos para imprimir.');
        }
        $logoDataUri = app(\App\Services\Images\BrandImage::class)->source('transfer');
        $lines = array_map(function (array $detail): array {
            $lot = is_array($detail['lote'] ?? null) ? $detail['lote'] : [];
            $product = is_array($lot['producto'] ?? null) ? $lot['producto'] : [];
            $quantity = (int) ($detail['cantidad'] ?? 0);
            $subtotal = (float) ($detail['subtotal'] ?? 0);

            return [
                'lot' => $lot,
                'product' => $product,
                'quantity' => $quantity,
                'cost' => $quantity > 0 ? $subtotal / $quantity : 0.0,
                'subtotal' => $subtotal,
                'isv' => (float) ($detail['isv'] ?? 0),
                'total' => (float) ($detail['total'] ?? 0),
            ];
        }, array_values(array_filter($details, is_array(...))));
        $totals = [
            'subtotal' => (float) ($transfer['subtotal'] ?? array_sum(array_column($lines, 'subtotal'))),
            'isv' => (float) ($transfer['isv'] ?? $transfer['isvtotal'] ?? array_sum(array_column($lines, 'isv'))),
            'total' => (float) ($transfer['total'] ?? array_sum(array_column($lines, 'total'))),
        ];
        $timezone = (string) config('farma.transfer_print_timezone', 'America/Tegucigalpa');
        $sentAt = ! empty($transfer['enviado_at']) ? Carbon::parse($transfer['enviado_at'])->timezone($timezone)->format('d/m/Y H:i:s') : 'Sin fecha';
        $receivedAt = ! empty($transfer['recibido_at']) ? Carbon::parse($transfer['recibido_at'])->timezone($timezone)->format('d/m/Y H:i:s') : 'Pendiente';

        return view('printing.transfer-'.$format, compact('transfer', 'lines', 'totals', 'logoDataUri', 'sentAt', 'receivedAt'))->render();
    }
}
