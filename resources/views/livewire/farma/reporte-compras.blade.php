<div class="farma-screen" data-test="purchase-report-screen">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><flux:heading size="xl">Reporte de compras</flux:heading>
        <flux:text class="mt-1">Consulta las facturas por proveedor y tipo de compra.</flux:text></div>
        @if ($canExport)<flux:button wire:click="openExport" icon="arrow-down-tray" :disabled="! $hasLoaded || $total < 1 || $loadError !== null">Exportar</flux:button>@endif
    </div>

    <div class="farma-table-panel rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="grid items-end gap-4 border-b border-slate-200 p-4 md:grid-cols-2 xl:grid-cols-4 dark:border-slate-800">
            <flux:input wire:model.live.debounce.500ms="search" label="Buscar" icon="magnifying-glass" placeholder="Factura, proveedor, producto o saldo…" maxlength="100" />
            <div>
                <label class="mb-2 block text-sm font-medium">Proveedor</label>
                <flux:button wire:click="openProviderPicker" class="w-full justify-between" icon-trailing="magnifying-glass" :disabled="! $canSelectProvider">{{ $providerName ?: 'Todos los proveedores' }}</flux:button>
            </div>
            <div>
                <flux:select wire:model.live="type" label="Tipo de compra">
                    <option value="">Todos los tipos</option>
                    <option value="1">Contado</option>
                    <option value="2">Crédito</option>
                </flux:select>
                @error('type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <flux:button wire:click="loadReport" variant="primary" icon="magnifying-glass" wire:loading.attr="disabled" wire:target="loadReport">Consultar</flux:button>
                <flux:button wire:click="clearFilters" icon="x-mark">Limpiar filtros</flux:button>
                <span class="text-sm text-slate-500">{{ number_format($total) }} facturas</span>
            </div>
        </div>
        <div wire:loading.delay wire:target="loadReport,search,type,selectProvider,previousPage,nextPage" class="px-4 py-2 text-sm text-slate-500" role="status">Actualizando reporte…</div>
        @if ($loadError)
            <div class="p-6 text-center text-sm text-red-600 dark:text-red-400" role="alert">{{ $loadError }}</div>
        @elseif (! $hasLoaded)
            <div class="p-10 text-center text-slate-500" data-test="purchase-report-idle">Selecciona filtros o pulsa Consultar para cargar las compras.</div>
        @else
            <div class="farma-table-scroll overflow-x-auto" tabindex="0">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead><tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3">Factura</th><th class="min-w-36 px-4 py-3">Estado</th><th class="px-4 py-3">Proveedor</th><th class="px-4 py-3">Tipo / plazo</th>
                        <th class="px-4 py-3 text-right">Subtotal</th><th class="px-4 py-3 text-right">ISV</th><th class="px-4 py-3 text-right">Descuento</th><th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Aplicado</th><th class="px-4 py-3 text-right">Saldo</th>
                        @if ($type === '2')<th class="px-4 py-3 text-right">Abonos</th>@endif
                        <th class="whitespace-nowrap px-4 py-3">Fecha de creación</th>
                        <th class="px-4 py-3">Pagos</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($purchases as $purchase)
                        <tr wire:key="report-purchase-{{ $purchase['id'] }}">
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $purchase['nro'] }}<span class="block text-xs font-normal text-slate-500">{{ $purchase['items'] }} unidades</span></td>
                            <td class="px-4 py-3"><flux:badge :color="match ($purchase['estado'] ?? '') { 'pagada' => 'green', 'vencida' => 'red', 'borrador' => 'zinc', default => 'amber' }">{{ $purchase['estado_label'] ?? 'Pendiente' }}</flux:badge></td>
                            <td class="px-4 py-3">{{ data_get($purchase, 'proveedor.nombre', '—') }}</td>
                            <td class="px-4 py-3">{{ data_get($purchase, 'tipo.descripcion', '—') }}<span class="block text-xs text-slate-500">{{ $purchase['plazo'] }} días</span></td>
                            @foreach (['subtotal', 'isv', 'descuento', 'total', 'total_aplicado', 'saldo'] as $amount)
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">L {{ number_format((float) ($purchase[$amount] ?? 0), 2) }}</td>
                            @endforeach
                            @if ($type === '2')<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">L {{ number_format((float) ($purchase['total_abonos'] ?? 0), 2) }}</td>@endif
                            <td class="whitespace-nowrap px-4 py-3">{{ ! empty($purchase['created_at']) ? Illuminate\Support\Carbon::parse($purchase['created_at'])->format('d/m/Y H:i') : '—' }}</td>
                            <td class="px-4 py-3"><flux:button wire:click="viewPayments({{ $purchase['id'] }})" size="sm" variant="ghost" icon="list-bullet" :disabled="! $canViewPayments" aria-label="Ver pagos de la factura {{ $purchase['nro'] }}">Ver pagos</flux:button></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $type === '2' ? 13 : 12 }}" class="p-10 text-center text-slate-500">No hay compras con los filtros seleccionados.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold dark:border-slate-700 dark:bg-slate-800/60" data-test="purchase-report-totals">
                        <tr>
                            <th colspan="4" scope="row" class="px-4 py-4 text-left">Totales de {{ number_format($total) }} facturas filtradas</th>
                            @foreach (['subtotal', 'isv', 'descuento', 'total'] as $amount)
                                <td class="whitespace-nowrap px-4 py-4 text-right tabular-nums">L {{ number_format((float) ($totals[$amount] ?? 0), 2) }}</td>
                            @endforeach
                            <td colspan="2"></td>
                            @if ($type === '2')<td class="whitespace-nowrap px-4 py-4 text-right tabular-nums">L {{ number_format((float) ($totals['abonos'] ?? 0), 2) }}</td>@endif
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-4 py-4 dark:border-slate-800">
                <div><p class="text-xs text-slate-500">Totales de todas las páginas · Descuentos incluidos</p><p class="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-400">Gran total: L {{ number_format((float) ($totals['gran_total'] ?? 0), 2) }}</p></div>
                @if ($type === '2')
                    <div data-test="purchase-report-abonos"><p class="text-sm text-slate-500">Total de abonos</p><p class="text-lg font-semibold">L {{ number_format((float) ($totals['abonos'] ?? 0), 2) }}</p></div>
                    <div data-test="purchase-report-balance"><p class="text-sm text-slate-500">Saldo pendiente</p><p class="text-lg font-semibold text-amber-700 dark:text-amber-400">L {{ number_format((float) ($totals['saldo_pendiente'] ?? 0), 2) }}</p></div>
                @endif
                <div class="flex items-center gap-3"><span class="text-sm text-slate-500">Página {{ $page }} de {{ $lastPage }}</span><flux:button wire:click="previousPage" size="sm" :disabled="$page <= 1">Anterior</flux:button><flux:button wire:click="nextPage" size="sm" :disabled="$page >= $lastPage">Siguiente</flux:button></div>
            </div>
        @endif
    </div>

    <flux:modal wire:model.self="showExportModal" class="md:w-lg">
        <form wire:submit="export" class="space-y-5">
            <div><flux:heading size="lg">Exportar reporte de compras</flux:heading><flux:text class="mt-1">Incluye todas las compras filtradas, sus pagos detallados y los totales generales.</flux:text></div>
            <flux:select wire:model="exportFormat" label="Formato del archivo"><option value="pdf">PDF</option><option value="xlsx">Excel (.xlsx)</option></flux:select>
            @error('exportFormat')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @if ($exportError)<p class="text-sm text-red-600" role="alert">{{ $exportError }}</p>@endif
            <p wire:loading.delay wire:target="export" class="text-sm text-slate-500" role="status">Preparando el archivo con todas las compras y pagos…</p>
            <div class="flex justify-end gap-2"><flux:button type="button" wire:click="$set('showExportModal', false)">Cancelar</flux:button><flux:button type="submit" variant="primary" icon="arrow-down-tray" wire:loading.attr="disabled" wire:target="export">Descargar</flux:button></div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showPaymentsModal" class="md:w-184">
        <div class="space-y-5">
            <div><flux:heading size="lg">Pagos de la factura {{ $paymentPurchase['nro'] ?? '' }}</flux:heading><flux:text class="mt-1">Pagos, abonos y notas de crédito aplicados.</flux:text></div>
            <div wire:loading.delay wire:target="viewPayments,previousPaymentPage,nextPaymentPage" class="text-sm text-slate-500" role="status">Cargando pagos…</div>
            @if ($paymentsError)
                <p class="text-sm text-red-600" role="alert">{{ $paymentsError }}</p>
            @else
                <div class="farma-table-scroll overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700" tabindex="0">
                    <table class="min-w-full text-sm"><thead><tr class="text-left text-xs uppercase text-slate-500"><th class="px-3 py-2">Fecha</th><th class="px-3 py-2">Tipo</th><th class="px-3 py-2">Comprobante</th><th class="px-3 py-2 text-right">Valor</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($paymentTransactions as $transaction)
                            <tr wire:key="report-payment-{{ $transaction['id'] }}"><td class="whitespace-nowrap px-3 py-2">{{ ! empty($transaction['created_at']) ? Illuminate\Support\Carbon::parse($transaction['created_at'])->format('d/m/Y H:i') : '—' }}</td><td class="px-3 py-2">{{ data_get($transaction, 'tipo.descripcion', '—') }}</td><td class="px-3 py-2">{{ $transaction['nro'] ?? '—' }}</td><td class="whitespace-nowrap px-3 py-2 text-right font-semibold">L {{ number_format((float) ($transaction['valor'] ?? 0), 2) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="p-8 text-center text-slate-500">Esta compra todavía no tiene movimientos.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap justify-between gap-3 rounded-lg bg-slate-50 px-4 py-3 dark:bg-slate-800"><span>Total de pagos aplicados: <strong>L {{ number_format((float) ($paymentPurchase['total_aplicado'] ?? 0), 2) }}</strong></span><span>Saldo de la factura: <strong>L {{ number_format((float) ($paymentPurchase['saldo'] ?? 0), 2) }}</strong></span></div>
                <div class="flex flex-wrap items-center justify-between gap-3"><span class="text-sm text-slate-500">Página {{ $paymentPage }} de {{ $paymentLastPage }}</span><div class="flex gap-2"><flux:button wire:click="previousPaymentPage" size="sm" :disabled="$paymentPage <= 1">Anterior</flux:button><flux:button wire:click="nextPaymentPage" size="sm" :disabled="$paymentPage >= $paymentLastPage">Siguiente</flux:button></div></div>
            @endif
            <div class="flex justify-end"><flux:button wire:click="closePayments">Cerrar</flux:button></div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showProviderPicker" class="md:w-xl">
        <div class="space-y-4">
            <flux:heading size="lg">Filtrar por proveedor</flux:heading>
            <flux:input wire:model.live.debounce.500ms="providerSearch" icon="magnifying-glass" placeholder="Buscar proveedor por nombre" maxlength="100" />
            @if ($providerLoadError)<p class="text-sm text-red-600" role="alert">{{ $providerLoadError }}</p>@endif
            <div class="farma-table-scroll max-h-96 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-700" tabindex="0">
                <table class="w-full text-sm"><thead class="text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Proveedor</th><th class="px-3 py-2 text-right">Acción</th></tr></thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($providerOptions as $option)
                        <tr wire:key="report-provider-{{ $option['id'] }}"><td class="px-3 py-3">{{ $option['nombre'] }}</td><td class="px-3 py-3 text-right"><flux:button wire:click="selectProvider({{ $option['id'] }})" size="sm" variant="primary">Seleccionar</flux:button></td></tr>
                    @empty
                        <tr><td colspan="2" class="px-3 py-8 text-center text-slate-500">No hay proveedores para mostrar.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3"><span class="text-xs text-slate-500">Página {{ $providerPage }} de {{ $providerLastPage }}</span><div class="flex gap-2"><flux:button wire:click="previousProviderPage" size="sm" :disabled="$providerPage <= 1">Anterior</flux:button><flux:button wire:click="nextProviderPage" size="sm" :disabled="$providerPage >= $providerLastPage">Siguiente</flux:button><flux:button wire:click="$set('showProviderPicker', false)" size="sm">Cerrar</flux:button></div></div>
        </div>
    </flux:modal>
</div>
