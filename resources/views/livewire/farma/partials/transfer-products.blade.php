<div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700">
        <h3 class="font-semibold">{{ $editable ? 'Detalle de la transferencia' : 'Productos transferidos' }}</h3>
        <span class="text-sm text-slate-500">{{ count($lines) }} lotes</span>
    </div>
    <div class="farma-table-scroll overflow-x-auto" tabindex="0">
        <table class="min-w-full text-sm" data-test="transfer-products-table">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="min-w-52 px-4 py-3">Producto</th>
                    <th class="min-w-52 px-4 py-3">Información del lote</th>
                    @if ($editable)
                        <th class="px-4 py-3 text-right">Disponible</th>
                    @endif
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="whitespace-nowrap px-4 py-3 text-right">Costo unitario</th>
                    <th class="px-4 py-3 text-right">Subtotal</th>
                    <th class="px-4 py-3 text-right">ISV</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    @if ($editable)
                        <th class="px-4 py-3 text-right">Acción</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($lines as $index => $line)
                    @php
                        $lot = $line['lot'];
                        $product = $line['product'];
                        $expiry = $lot['fecha_expiracion'] ?? $lot['fecha_exp'] ?? null;
                        $manufactured = $lot['fecha_elaboracion'] ?? $lot['fecha_ela'] ?? null;
                    @endphp
                    <tr wire:key="{{ $editable ? 'draft' : 'saved' }}-transfer-line-{{ $lot['id'] ?? $index }}">
                        <td class="px-4 py-3 align-top">
                            <p class="font-semibold text-slate-900 dark:text-white">{{ data_get($product, 'descripcion', 'Producto seleccionado') }}</p>
                            <p class="mt-1 text-xs text-slate-500">Código: {{ data_get($product, 'codigo', '—') }}</p>
                        </td>
                        <td class="px-4 py-3 align-top">
                            <p class="font-mono font-semibold text-cyan-700 dark:text-cyan-300">{{ $lot['lote'] ?? $lot['codigo'] ?? '—' }}</p>
                            <div class="mt-1 space-y-0.5 whitespace-nowrap text-xs text-slate-500">
                                <p>Elaboración: {{ $manufactured ? Illuminate\Support\Carbon::parse($manufactured)->format('d/m/Y') : '—' }}</p>
                                <p>Expiración: {{ $expiry ? Illuminate\Support\Carbon::parse($expiry)->format('d/m/Y') : '—' }}</p>
                                @if (data_get($lot, 'factura.nro'))
                                    <p>Factura: {{ data_get($lot, 'factura.nro') }}</p>
                                @endif
                            </div>
                        </td>
                        @if ($editable)
                            <td class="px-4 py-3 text-right align-top">{{ number_format((int) ($lot['cantidad'] ?? 0)) }}</td>
                        @endif
                        <td class="px-4 py-3 text-right align-top">
                            @if ($editable)
                                <flux:input wire:model.live.debounce.400ms="details.{{ $index }}.cantidad" type="number" min="1" max="{{ (int) ($lot['cantidad'] ?? 0) }}" class="w-24" aria-label="Cantidad del lote {{ $lot['lote'] ?? '' }}" />
                                @error("details.{$index}.cantidad")
                                    <p class="mt-1 max-w-44 text-left text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            @else
                                {{ number_format($line['quantity']) }}
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right align-top">{{ $line['cost'] !== null ? 'L '.number_format($line['cost'], 2) : 'Sin costo' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right align-top">L {{ number_format($line['subtotal'], 2) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right align-top">
                            L {{ number_format($line['isv'], 2) }}
                            <span class="mt-1 block text-xs {{ $line['taxable'] ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500' }}">{{ $line['taxable'] ? 'Gravado' : 'Exento' }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold align-top">L {{ number_format($line['total'], 2) }}</td>
                        @if ($editable)
                            <td class="px-4 py-3 text-right align-top">
                                <flux:button type="button" wire:click="removeLot({{ $index }})" variant="ghost" size="sm" icon="trash" aria-label="Quitar producto" />
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $editable ? 9 : 7 }}" class="px-4 py-10 text-center text-slate-500">
                            {{ $editable ? 'Selecciona un producto y agrega sus lotes y cantidades.' : 'No se encontraron productos en el detalle.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
