<div class="farma-screen" data-test="purchases-screen">
    <style>
        .purchases-form-modal {
            width: calc(100vw - 2rem) !important;
            max-width: calc(100vw - 2rem) !important;
        }

        @media (min-width: 64rem) {
            dialog.purchases-content-modal {
                inset-inline-start: 17rem !important;
            }

            .purchases-form-modal {
                width: calc(100vw - 19rem) !important;
                max-width: calc(100vw - 19rem) !important;
            }
        }
    </style>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Compras</flux:heading>
            <flux:text class="mt-1">Facturas de proveedores, productos, saldos y documentos escaneados.</flux:text>
        </div>
        @if ($access['create'])
            <flux:button wire:click="openCreate" variant="primary" icon="plus">Nueva compra</flux:button>
        @endif
    </div>

    <div class="farma-table-panel rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" placeholder="Buscar factura, proveedor, tipo, usuario, producto o saldo…" class="sm:max-w-md" />
            <span class="text-sm text-slate-500 dark:text-slate-400">{{ number_format($total) }} compras registradas</span>
        </div>
        @if ($loadError)
            <div class="p-6 text-center text-sm text-red-600 dark:text-red-400">{{ $loadError }}</div>
        @elseif ($visiblePurchases === [])
            <div class="p-10 text-center text-slate-500">No hay compras para mostrar.</div>
        @else
            <div class="farma-table-scroll overflow-x-auto" tabindex="0">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead><tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3">Factura</th><th class="min-w-36 px-4 py-3">Estado</th><th class="px-4 py-3">Proveedor</th><th class="px-4 py-3">Tipo / plazo</th>
                        <th class="px-4 py-3 text-right">Subtotal</th><th class="px-4 py-3 text-right">ISV</th><th class="px-4 py-3 text-right">Descuento</th><th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Aplicado</th><th class="px-4 py-3 text-right">Saldo</th><th class="px-4 py-3 whitespace-nowrap">Fecha de creación</th><th class="px-2 py-3 text-left">Acciones</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($visiblePurchases as $purchase)
                        <tr wire:key="purchase-{{ $purchase['id'] }}">
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $purchase['nro'] }}<span class="block text-xs font-normal text-slate-500">{{ $purchase['items'] }} unidades</span></td>
                            <td class="min-w-36 px-4 py-3">
                                @switch($purchase['estado'] ?? 'pendiente')
                                    @case('pagada') <flux:badge color="green" size="sm" icon="check-circle">Pagada</flux:badge> @break
                                    @case('vencida') <flux:badge color="red" size="sm" icon="exclamation-triangle">Vencida</flux:badge> @break
                                    @case('borrador') <flux:badge color="zinc" size="sm" icon="pencil-square">Borrador</flux:badge> @break
                                    @default <flux:badge color="amber" size="sm" icon="clock">Pendiente</flux:badge>
                                @endswitch
                                @if (! empty($purchase['vence_el']) && ($purchase['estado'] ?? '') !== 'borrador')<span class="mt-1 block whitespace-nowrap text-xs text-slate-500">Vence {{ $purchase['vence_el'] }}</span>@endif
                            </td>
                            <td class="px-4 py-3">{{ data_get($purchase, 'proveedor.nombre', '—') }}</td>
                            <td class="px-4 py-3">{{ data_get($purchase, 'tipo.descripcion', '—') }}<span class="block text-xs text-slate-500">{{ $purchase['plazo'] }} días</span></td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">L {{ number_format((float) $purchase['subtotal'], 2) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">L {{ number_format((float) $purchase['isv'], 2) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-rose-600 dark:text-rose-400">− L {{ number_format((float) ($purchase['descuento'] ?? 0), 2) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-semibold">L {{ number_format((float) $purchase['total'], 2) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">L {{ number_format((float) $purchase['total_aplicado'], 2) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-semibold {{ (float) $purchase['saldo'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">L {{ number_format((float) $purchase['saldo'], 2) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ ! empty($purchase['created_at']) ? Illuminate\Support\Carbon::parse($purchase['created_at'])->format('d/m/Y') : '—' }}</td>
                            <td class="px-2 py-3"><div class="flex min-w-56 flex-wrap items-center justify-start gap-0">
                                @if ($access['report'])<flux:button wire:click="openReport({{ $purchase['id'] }})" size="sm" variant="ghost" icon="document-chart-bar" aria-label="Ver detalle de compra" title="Ver detalle" />@endif
                                @if ($access['kardex'] && empty($purchase['kardex_enviado']) && ($purchase['estado'] ?? '') !== 'borrador')<flux:button wire:click="confirmKardex({{ $purchase['id'] }})" size="sm" variant="ghost" icon="arrow-up-tray" aria-label="Enviar a Kardex" title="Enviar a Lotes" data-test="send-purchase-kardex" />@elseif (! empty($purchase['kardex_enviado']))<span class="inline-flex size-8 items-center justify-center text-emerald-600 dark:text-emerald-400" title="Enviada a Kardex" aria-label="Enviada a Kardex"><flux:icon.check-circle class="size-5" /></span>@endif
                                @if ($access['pay'] && in_array(($purchase['estado'] ?? ''), ['pendiente', 'vencida'], true) && (float) $purchase['saldo'] > 0)<flux:button wire:click="openPayment({{ $purchase['id'] }})" size="sm" variant="ghost" icon="plus-circle" aria-label="Agregar pago" title="Agregar pago" />@endif
                                @if ($access['viewPayments'] && ($purchase['estado'] ?? '') !== 'borrador')<flux:button wire:click="viewPayments({{ $purchase['id'] }})" size="sm" variant="ghost" icon="list-bullet" aria-label="Ver pagos" title="Ver pagos" />@endif
                                @if ($access['document'] && $purchase['tiene_documento'])<flux:button wire:click="viewDocument({{ $purchase['id'] }})" data-image-viewer-key="purchases:{{ $purchase['id'] }}" data-image-viewer-src="{{ $purchase['documento_url'] ?? '' }}" data-image-viewer-name="Factura {{ $purchase['nro'] }}" size="sm" variant="ghost" icon="document-magnifying-glass" aria-label="Ver factura" />@endif
                                @if ($access['edit'] && ($purchase['estado'] ?? '') !== 'pagada' && empty($purchase['kardex_enviado']))<flux:button wire:click="openEdit({{ $purchase['id'] }})" size="sm" variant="ghost" icon="pencil-square" aria-label="Modificar" title="Modificar" />@endif
                                @if ($access['delete'] && empty($purchase['kardex_enviado']))<flux:button wire:click="confirmDelete({{ $purchase['id'] }})" size="sm" variant="ghost" icon="trash" aria-label="Eliminar" />@endif
                            </div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800">
                <span class="text-sm text-slate-500">Página {{ $page }} de {{ $lastPage }}</span>
                <div class="flex gap-2"><flux:button wire:click="previousPage" size="sm" :disabled="$page <= 1">Anterior</flux:button><flux:button wire:click="nextPage" size="sm" :disabled="$page >= $lastPage">Siguiente</flux:button></div>
            </div>
        @endif
    </div>

    <flux:modal wire:model.self="showFormModal" class="purchases-content-modal purchases-form-modal bg-white! dark:bg-slate-900!">
        <form wire:submit="save" class="space-y-6">
            <div class="flex items-center gap-3">@if ($providerImageSrc)<img src="{{ $providerImageSrc }}" alt="Imagen del proveedor seleccionado" class="size-16 rounded-xl border border-slate-200 bg-white object-contain p-1 shadow-sm dark:border-slate-700" />@endif<div><flux:heading size="lg">{{ $editingId ? 'Modificar compra' : 'Nueva compra' }}</flux:heading><flux:text class="mt-1">Los datos se guardarán al pulsar Finalizar compra.</flux:text></div></div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <x-remote-select source="purchase-types" cache="options.tipos" wire:model="tipo" label="Tipo de compra" />
                <div><label class="mb-2 block text-sm font-medium">Proveedor</label><flux:button type="button" wire:click="openProviderPicker" class="w-full justify-between" icon-trailing="magnifying-glass">{{ $providerName !== '' ? $providerName : 'Seleccionar proveedor' }}</flux:button>@error('proveedor')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <flux:input wire:model="nro" label="Número de factura" maxlength="255" />
                <flux:input wire:model="plazo" type="number" min="0" max="3650" label="Plazo de pago (días)" />
            </div>
            <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700"><div><p class="font-medium">Detalle de productos</p><p class="text-xs text-slate-500">Cada total se calcula como cantidad × (costo + ISV unitario − descuento unitario).</p></div><flux:button type="button" wire:click="addDetail" size="sm" icon="plus">Agregar producto</flux:button></div>
                <div class="farma-table-scroll overflow-x-auto" tabindex="0"><table class="w-420 min-w-full table-fixed text-xs"><colgroup><col class="w-96" /><col class="w-28" /><col class="w-24" /><col class="w-20" /><col class="w-24" /><col class="w-32" /><col class="w-40" /><col class="w-40" /><col class="w-28" /><col class="w-24" /><col class="w-28" /><col class="w-24" /><col class="w-12" /></colgroup><thead><tr class="text-left text-[11px] uppercase leading-tight text-slate-500"><th class="px-2 py-2">Nombre</th><th class="px-2 py-2">Presentación</th><th class="px-2 py-2">Unidad de compra</th><th class="px-2 py-2">Cantidad</th><th class="px-2 py-2">Costo</th><th class="px-2 py-2">Lote</th><th class="px-2 py-2">Fecha de elaboración</th><th class="px-2 py-2">Fecha de expiración</th><th class="px-2 py-2">Gravado / ISV c/u</th><th class="px-2 py-2">Descuento c/u</th><th class="px-2 py-2 text-right">Total</th><th class="px-2 py-2 text-center">Bonificación</th><th class="px-2 py-2"></th></tr></thead><tbody>
                @foreach ($details as $index => $detail)
                    <tr wire:key="purchase-detail-{{ $index }}" class="border-t border-slate-100 dark:border-slate-800">
                        <td class="p-1.5">
                            <button
                                type="button"
                                wire:click="openProductSearch({{ $index }})"
                                class="flex min-h-8 w-full items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-left text-xs shadow-sm transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500/40 dark:border-slate-600 dark:bg-slate-900 dark:hover:border-slate-500 dark:hover:bg-slate-800"
                                data-test="open-product-search"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="block wrap-break-word leading-5 {{ empty($detail['producto']) ? 'text-slate-500' : 'font-medium text-slate-800 dark:text-slate-100' }}">
                                        {{ $detailProducts[$index]['name'] ?? 'Buscar producto' }}
                                        @if (! empty($detailProducts[$index]['laboratory']))
                                            <span class="ml-1 font-normal text-emerald-700 dark:text-emerald-400">· {{ $detailProducts[$index]['laboratory'] }}</span>
                                        @endif
                                    </span>
                                    @if (($detailProducts[$index]['code'] ?? '') !== '')
                                        <span class="mt-0.5 block wrap-break-word font-mono text-[11px] text-slate-500 dark:text-slate-400">Código: {{ $detailProducts[$index]['code'] }}</span>
                                    @endif
                                </span>
                                <flux:icon.magnifying-glass class="size-4 shrink-0 text-slate-400" />
                            </button>
                            @error("details.{$index}.producto")<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </td>
                        <td class="wrap-break-word p-1.5 font-medium text-slate-700 dark:text-slate-200">{{ $detailProducts[$index]['presentation'] ?? '—' }}</td>
                        <td class="wrap-break-word p-1.5 font-medium text-slate-700 dark:text-slate-200">{{ $detailProducts[$index]['purchaseUnit'] ?? '—' }}</td>
                        <td class="p-1.5"><flux:input wire:model.live.debounce.1500ms="details.{{ $index }}.cantidad" type="number" min="1" size="sm" /></td>
                        <td class="p-1.5">
                            <flux:input wire:model.live.debounce.1500ms="details.{{ $index }}.costo" type="number" :min="! empty($detail['es_bonificacion']) ? 0 : 0.01" step="0.01" size="sm" />
                            @if (isset($productCostChanges[$index]))
                                @php
                                    $costChange = $productCostChanges[$index];
                                @endphp
                                <p class="mt-1 text-[11px] leading-tight {{ $costChange['difference'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400' }}" data-test="purchase-cost-difference" title="Último costo: L {{ number_format((float) $costChange['previous'], 2) }}">
                                    {{ $costChange['difference'] > 0 ? 'Subió' : 'Bajó' }} L {{ number_format(abs($costChange['difference']), 2) }}
                                    <span class="mt-0.5 block text-slate-500 dark:text-slate-400">Anterior: L {{ number_format((float) $costChange['previous'], 2) }}</span>
                                </p>
                            @elseif (isset($productCostErrors[(int) ($detail['producto'] ?? 0)]))
                                <p class="mt-1 text-[11px] leading-tight text-amber-700 dark:text-amber-400">{{ $productCostErrors[(int) $detail['producto']] }}</p>
                            @elseif (! empty($detail['producto']) && array_key_exists((int) $detail['producto'], $latestProductCosts) && $latestProductCosts[(int) $detail['producto']] === null)
                                <p class="mt-1 text-[11px] leading-tight text-slate-500 dark:text-slate-400">Sin compras anteriores</p>
                            @endif
                            @error("details.{$index}.costo")<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </td>
                        <td class="p-1.5"><flux:input wire:model.live.debounce.3000ms="details.{{ $index }}.lote" maxlength="100" size="sm" /></td>
                        <td class="p-1.5"><flux:input wire:model.live="details.{{ $index }}.fecha_elaboracion" type="date" size="sm" />@error("details.{$index}.fecha_elaboracion")<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</td>
                        <td class="p-1.5"><flux:input wire:model.live="details.{{ $index }}.fecha_expiracion" type="date" size="sm" />@error("details.{$index}.fecha_expiracion")<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</td>
                        <td class="p-1.5"><div class="flex items-center gap-1.5"><flux:checkbox wire:model.live="details.{{ $index }}.isv" /><span class="whitespace-nowrap text-xs font-medium text-slate-600 dark:text-slate-300">L {{ number_format((float) ($purchaseTotals['unitTaxes'][$index] ?? 0), 2) }}</span></div></td>
                        <td class="p-1.5"><flux:input wire:model.live.debounce.1500ms="details.{{ $index }}.descuento" type="number" min="0" step="0.01" size="sm" />@error("details.{$index}.descuento")<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</td>
                        <td class="whitespace-nowrap p-1.5 text-right font-semibold text-slate-900 dark:text-white">L {{ number_format((float) ($purchaseTotals['lines'][$index] ?? 0), 2) }}</td>
                        <td class="p-1.5 text-center"><flux:checkbox wire:model.live="details.{{ $index }}.es_bonificacion" aria-label="Bonificación del producto {{ $index + 1 }}" title="Este producto y lote es bonificación; no acepta devolución" /></td>
                        <td class="p-1.5"><flux:button type="button" wire:click="removeDetail({{ $index }})" size="sm" variant="ghost" icon="x-mark" aria-label="Quitar" /></td>
                    </tr>
                @endforeach
                </tbody></table></div>
                <div class="grid border-t border-slate-200 bg-slate-50 sm:grid-cols-2 xl:grid-cols-4 dark:border-slate-700 dark:bg-slate-800/40">
                    <div class="px-5 py-4"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Subtotal</p><p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">L {{ number_format((float) $purchaseTotals['subtotal'], 2) }}</p></div>
                    <div class="border-slate-200 px-5 py-4 sm:border-l dark:border-slate-700"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">ISV</p><p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">L {{ number_format((float) $purchaseTotals['isv'], 2) }}</p></div>
                    <div class="border-slate-200 px-5 py-4 xl:border-l dark:border-slate-700"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Descuento</p><p class="mt-1 text-lg font-semibold text-rose-600 dark:text-rose-400">− L {{ number_format((float) $purchaseTotals['descuento'], 2) }}</p></div>
                    <div class="border-slate-200 px-5 py-4 sm:border-l dark:border-slate-700"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total a pagar</p><p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">L {{ number_format((float) $purchaseTotals['total'], 2) }}</p></div>
                </div>
            </div>
            <flux:textarea wire:model="nota" label="Nota" rows="4" maxlength="5000" />
            <div x-data class="rounded-lg border border-dashed border-slate-300 p-4 dark:border-slate-700">
                <label class="block text-sm font-medium">Escanear o seleccionar factura</label><p class="mb-3 text-xs text-slate-500">JPG o PNG</p>
                <input x-ref="invoiceImage" type="file" wire:model="documentoArchivo" accept="image/jpeg,image/png" capture="environment" class="sr-only" tabindex="-1" />
                <flux:button type="button" x-on:click="$refs.invoiceImage.click()" icon="arrow-up-tray" size="sm" wire:loading.attr="disabled" wire:target="documentoArchivo">Cargar imagen</flux:button>
                @error('documentoArchivo')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                @if ($documentoBase64 && $documentoMime)<img src="data:{{ $documentoMime }};base64,{{ $documentoBase64 }}" alt="Vista previa de factura" class="mt-3 h-32 w-auto rounded border object-contain" />@endif
            </div>
            <div class="flex justify-end gap-2"><flux:button type="button" wire:click="closeForm">Cerrar</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Finalizar compra</flux:button></div>
        </form>
    </flux:modal>

    @if ($showProviderPicker)
        <flux:modal wire:model.self="showProviderPicker" class="purchases-content-modal md:w-xl">
            <div class="space-y-4">
                <div><flux:heading size="lg">Seleccionar proveedor</flux:heading><flux:text class="mt-1">Busca por nombre y selecciona un proveedor.</flux:text></div>
                <flux:input wire:model.live.debounce.500ms="providerSearch" icon="magnifying-glass" placeholder="Nombre del proveedor" />
                @if ($providerLoadError)<p class="text-sm text-red-600">{{ $providerLoadError }}</p>@endif
                <div class="farma-table-scroll max-h-96 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-700" tabindex="0">
                    <table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800"><tr><th class="px-3 py-2">Nombre</th><th class="px-3 py-2 text-right">Acción</th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse ($providerOptions as $option)
                            <tr wire:key="purchase-provider-{{ $option['id'] }}"><td class="px-3 py-3">{{ $option['nombre'] ?? $option['label'] ?? '—' }}</td><td class="px-3 py-3 text-right"><flux:button type="button" wire:click="selectProvider({{ $option['id'] }})" size="sm" variant="primary">Seleccionar</flux:button></td></tr>
                        @empty
                            <tr><td colspan="2" class="px-3 py-8 text-center text-slate-500">No hay proveedores para mostrar.</td></tr>
                        @endforelse
                    </tbody></table>
                </div>
                <div class="flex items-center justify-between gap-3"><span class="text-xs text-slate-500">{{ $providerTotal }} resultados · Página {{ $providerPage }} de {{ $providerLastPage }}</span><div class="flex gap-2"><flux:button type="button" wire:click="previousProviderPage" size="sm" :disabled="$providerPage <= 1">Anterior</flux:button><flux:button type="button" wire:click="nextProviderPage" size="sm" :disabled="$providerPage >= $providerLastPage">Siguiente</flux:button><flux:button type="button" wire:click="closeProviderPicker" size="sm">Cerrar</flux:button></div></div>
            </div>
        </flux:modal>
    @endif

    <flux:modal wire:model.self="showPaymentModal" class="md:w-136">
        <form wire:submit="savePayment" class="space-y-5">
            <div><flux:heading size="lg">Registrar pago</flux:heading><flux:text class="mt-1">Factura {{ $paymentPurchaseName }}</flux:text></div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/60 dark:bg-amber-950/30"><p class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-400">Saldo disponible</p><p class="mt-1 text-xl font-bold text-amber-800 dark:text-amber-300">L {{ number_format($paymentBalance, 2) }}</p></div>
            <x-remote-select source="payment-types" cache="paymentOptions.tipos" wire:model.live="paymentType" label="Tipo de transacción" />
            <div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="paymentNumber" label="Número de comprobante" maxlength="100" /><flux:input wire:model="paymentValue" type="number" min="0.01" :max="$paymentCash ? $paymentTotal : $paymentBalance" step="0.01" label="Valor" :readonly="$paymentCash" /></div>
            @if ($paymentCash)<p class="text-sm text-blue-700 dark:text-blue-300">El pago al contado se registra únicamente por el total exacto de la factura: <strong>L {{ number_format($paymentTotal, 2) }}</strong>.</p>@endif
            @if ($paymentDocumentRequired)
                <div class="rounded-lg border border-dashed border-amber-300 bg-amber-50/60 p-4 dark:border-amber-800 dark:bg-amber-950/20">
                    <label class="block text-sm font-semibold text-amber-900 dark:text-amber-200">Comprobante obligatorio</label>
                    <p class="mb-3 text-xs text-amber-700 dark:text-amber-400">Los abonos y las notas de crédito deben incluir una imagen JPG o PNG.</p>
                    <input type="file" wire:model="paymentDocumentFile" accept="image/jpeg,image/png" capture="environment" class="block w-full text-sm" />
                    @error('paymentDocumentFile')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    @if ($paymentDocumentBase64 && $paymentDocumentMime)<img src="data:{{ $paymentDocumentMime }};base64,{{ $paymentDocumentBase64 }}" alt="Vista previa del comprobante" class="mt-3 h-32 w-auto rounded border object-contain" />@endif
                </div>
            @else
                <p class="text-sm text-slate-500 dark:text-slate-400">El pago al contado no requiere fotografía del comprobante.</p>
            @endif
            <div class="flex justify-end gap-2"><flux:button type="button" wire:click="closePayment">Cancelar</flux:button><flux:button type="submit" variant="primary" icon="banknotes">Registrar pago</flux:button></div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showReportModal" class="purchases-content-modal purchases-form-modal bg-white! dark:bg-slate-900!">
        @if ($reportPurchase !== [])
            <div class="space-y-6" data-test="purchase-report">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5 dark:border-slate-700">
                    <div class="flex items-center gap-3">
                        <div class="flex size-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300"><flux:icon.document-chart-bar class="size-6" /></div>
                        <div><flux:heading size="xl">Reporte de compra</flux:heading><flux:text class="mt-1">Factura {{ $reportPurchase['nro'] ?? '—' }}</flux:text></div>
                    </div>
                    <flux:badge :color="match ($reportPurchase['estado'] ?? 'pendiente') { 'pagada' => 'green', 'vencida' => 'red', 'borrador' => 'zinc', default => 'amber' }">{{ $reportPurchase['estado_label'] ?? 'Pendiente' }}</flux:badge>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-xs uppercase text-slate-500">Proveedor</p><p class="mt-1 font-semibold">{{ data_get($reportPurchase, 'proveedor.nombre', '—') }}</p></div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-xs uppercase text-slate-500">Tipo / plazo de pago</p><p class="mt-1 font-semibold">{{ data_get($reportPurchase, 'tipo.descripcion', '—') }} · {{ $reportPurchase['plazo'] ?? 0 }} días</p></div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-xs uppercase text-slate-500">Ingresada por</p><p class="mt-1 font-semibold">{{ data_get($reportPurchase, 'usuario.nombre', '—') }}</p></div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-xs uppercase text-slate-500">Fecha de creación</p><p class="mt-1 font-semibold">{{ ! empty($reportPurchase['created_at']) ? Illuminate\Support\Carbon::parse($reportPurchase['created_at'])->format('d/m/Y H:i') : '—' }}</p></div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60"><p class="text-xs uppercase text-slate-500">Subtotal</p><p class="mt-1 whitespace-nowrap font-semibold">L {{ number_format((float) ($reportPurchase['subtotal'] ?? 0), 2) }}</p></div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60"><p class="text-xs uppercase text-slate-500">ISV</p><p class="mt-1 whitespace-nowrap font-semibold">L {{ number_format((float) ($reportPurchase['isv'] ?? 0), 2) }}</p></div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60"><p class="text-xs uppercase text-slate-500">Descuento</p><p class="mt-1 whitespace-nowrap font-semibold text-rose-600">− L {{ number_format((float) ($reportPurchase['descuento'] ?? 0), 2) }}</p></div>
                    <div class="rounded-lg bg-blue-50 p-3 dark:bg-blue-950/30"><p class="text-xs uppercase text-blue-600 dark:text-blue-300">Total</p><p class="mt-1 whitespace-nowrap font-bold text-blue-700 dark:text-blue-200">L {{ number_format((float) ($reportPurchase['total'] ?? 0), 2) }}</p></div>
                    <div class="rounded-lg bg-amber-50 p-3 dark:bg-amber-950/30"><p class="text-xs uppercase text-amber-600 dark:text-amber-300">Saldo</p><p class="mt-1 whitespace-nowrap font-bold text-amber-700 dark:text-amber-200">L {{ number_format((float) ($reportPurchase['saldo'] ?? 0), 2) }}</p></div>
                </div>

                <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                    <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700"><p class="font-semibold">Productos ingresados</p><p class="text-xs text-slate-500">{{ count($reportPurchase['detalles'] ?? []) }} productos en el detalle de la compra.</p></div>
                    <div class="farma-table-scroll overflow-x-auto" tabindex="0"><table class="w-7xl table-fixed text-xs" data-test="purchase-report-products-table"><colgroup><col class="w-72" /><col class="w-20" /><col class="w-32" /><col class="w-32" /><col class="w-32" /><col class="w-24" /><col class="w-28" /><col class="w-24" /><col class="w-28" /><col class="w-28" /></colgroup><thead><tr class="text-left text-[11px] uppercase leading-tight text-slate-500"><th class="px-2 py-2">Producto</th><th class="px-2 py-2 text-right">Cantidad</th><th class="px-2 py-2">Lote</th><th class="px-2 py-2">Elaboración</th><th class="px-2 py-2">Expiración</th><th class="px-2 py-2 text-right">Costo</th><th class="px-2 py-2 text-right">Subtotal</th><th class="px-2 py-2 text-right">ISV</th><th class="px-2 py-2 text-right">Descuento</th><th class="px-2 py-2 text-right">Total</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse (($reportPurchase['detalles'] ?? []) as $detail)
                            @php
                                $productMetadata = array_values(array_filter([
                                    data_get($detail, 'producto.laboratorio'),
                                    data_get($detail, 'producto.presentacion'),
                                    data_get($detail, 'producto.unidad_compra'),
                                ], static fn ($value) => filled($value)));
                            @endphp
                            <tr wire:key="purchase-report-detail-{{ $detail['id'] ?? $loop->index }}"><td class="px-2 py-2 align-top"><p class="font-semibold text-slate-900 dark:text-white">{{ data_get($detail, 'producto.descripcion', '—') }}</p>@if (! empty($detail['es_bonificacion']))<span class="mt-1 inline-block text-xs font-semibold text-amber-700 dark:text-amber-300">Bonificación · Sin devolución</span>@endif<p class="mt-0.5 truncate text-[11px] leading-tight text-slate-500" title="{{ implode(' · ', $productMetadata) }}">{{ $productMetadata !== [] ? implode(' · ', $productMetadata) : 'Sin información adicional' }}</p></td><td class="px-2 py-2 text-right">{{ $detail['cantidad'] ?? 0 }}</td><td class="truncate px-2 py-2" title="{{ $detail['lote'] ?? '—' }}">{{ $detail['lote'] ?? '—' }}</td><td class="px-2 py-2 whitespace-nowrap">{{ ! empty($detail['fecha_elaboracion']) ? Illuminate\Support\Carbon::parse($detail['fecha_elaboracion'])->format('d/m/Y') : '—' }}</td><td class="px-2 py-2 whitespace-nowrap">{{ ! empty($detail['fecha_expiracion']) ? Illuminate\Support\Carbon::parse($detail['fecha_expiracion'])->format('d/m/Y') : '—' }}</td><td class="px-2 py-2 whitespace-nowrap text-right">L {{ number_format((float) ($detail['costo'] ?? 0), 2) }}</td><td class="px-2 py-2 whitespace-nowrap text-right">L {{ number_format((float) ($detail['subtotal'] ?? 0), 2) }}</td><td class="px-2 py-2 whitespace-nowrap text-right">L {{ number_format((float) ($detail['isv_valor'] ?? 0), 2) }}</td><td class="px-2 py-2 whitespace-nowrap text-right">L {{ number_format((float) ($detail['descuento_valor'] ?? 0), 2) }}</td><td class="px-2 py-2 whitespace-nowrap text-right font-semibold">L {{ number_format((float) ($detail['total'] ?? 0), 2) }}</td></tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-8 text-center text-slate-500">La compra no tiene productos registrados.</td></tr>
                        @endforelse
                    </tbody></table></div>
                </div>

                @if (! empty($reportPurchase['nota']))<div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"><p class="text-xs uppercase text-slate-500">Nota</p><p class="mt-1 whitespace-pre-line text-sm">{{ $reportPurchase['nota'] }}</p></div>@endif
                <div class="flex justify-end"><flux:button wire:click="closeReport">Cerrar</flux:button></div>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model.self="showPaymentsModal" class="md:w-184">
        <div class="space-y-5">
            <div><flux:heading size="lg">Pagos de la factura {{ $paymentPurchaseName }}</flux:heading><flux:text class="mt-1">Pagos, abonos y notas de crédito aplicados.</flux:text></div>
            @if ($paymentTransactions === [])
                <div class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700">Esta compra todavía no tiene movimientos.</div>
            @else
                <div class="farma-table-scroll overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700" tabindex="0"><table class="min-w-full text-sm"><thead><tr class="text-left text-xs uppercase text-slate-500"><th class="px-3 py-2">Fecha</th><th class="px-3 py-2">Tipo</th><th class="px-3 py-2">Comprobante</th><th class="px-3 py-2 text-right">Valor</th><th class="px-3 py-2"></th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($paymentTransactions as $transaction)<tr wire:key="purchase-payment-{{ $transaction['id'] }}"><td class="px-3 py-2">{{ substr((string) ($transaction['created_at'] ?? ''), 0, 10) }}</td><td class="px-3 py-2">{{ data_get($transaction, 'tipo.descripcion', '—') }}</td><td class="px-3 py-2">{{ $transaction['nro'] ?: '—' }}</td><td class="px-3 py-2 text-right font-semibold">L {{ number_format((float) $transaction['valor'], 2) }}</td><td class="px-3 py-2 text-right">@if ($access['paymentDocument'] && $transaction['tiene_documento'])<flux:button wire:click="viewPaymentDocument({{ $transaction['id'] }})" data-image-viewer-key="purchase-payments:{{ $transaction['id'] }}" data-image-viewer-src="{{ $transaction['documento_url'] ?? '' }}" data-image-viewer-name="Comprobante de pago" size="sm" variant="ghost" icon="document-magnifying-glass" aria-label="Ver comprobante" />@endif</td></tr>@endforeach
                </tbody></table></div>
            @endif
            <div class="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900/60 dark:bg-emerald-950/30"><span class="text-sm font-medium text-emerald-800 dark:text-emerald-300">Total de pagos aplicados</span><span class="text-xl font-bold text-emerald-800 dark:text-emerald-300">L {{ number_format($paymentsTotal, 2) }}</span></div>
            <div class="flex justify-end"><flux:button wire:click="closePayments">Cerrar</flux:button></div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-120"><div class="space-y-6"><div><flux:heading size="lg">Eliminar compra</flux:heading><flux:text class="mt-2">¿Deseas eliminar la factura <strong>{{ $deletingName }}</strong>? No se eliminará si tiene pagos, abonos o notas relacionados.</flux:text></div><div class="flex justify-end gap-2"><flux:button wire:click="cancelDelete">Cancelar</flux:button><flux:button wire:click="delete" variant="danger">Eliminar</flux:button></div></div></flux:modal>

    <flux:modal wire:model.self="showKardexModal" class="md:w-136">
        <div class="space-y-6" data-test="purchase-kardex-confirmation">
            <div><flux:heading size="lg">Enviar compra a Lotes</flux:heading><flux:text class="mt-2">La factura <strong>{{ $kardexPurchaseName }}</strong> generará sus lotes con producto, cantidad y fechas de elaboración y expiración.</flux:text></div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300">Esta acción solo se puede realizar una vez. Después del envío la compra no podrá modificarse ni eliminarse.</div>
            <div class="flex justify-end gap-2"><flux:button wire:click="cancelKardex">Cancelar</flux:button><flux:button wire:click="sendToKardex" variant="primary" icon="arrow-up-tray">Enviar</flux:button></div>
        </div>
    </flux:modal>

    <livewire:shared.advanced-product-search />
</div>
