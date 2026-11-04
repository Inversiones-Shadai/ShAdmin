<div class="farma-screen" data-test="product-cost-report">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-medium text-cyan-700 dark:text-cyan-300">Farma · Reportes</p>
            <flux:heading size="xl" class="mt-1">Costeo de productos</flux:heading>
            <flux:text class="mt-2">Revisa cómo cambia el costo de un producto en cada compra y si incluye ISV.</flux:text>
        </div>
        <flux:button wire:click="openFilters" icon="adjustments-horizontal" variant="primary" data-test="cost-filters-button">Filtros</flux:button>
    </header>
    <div class="farma-screen-body space-y-6">

        <flux:modal wire:model.self="showFiltersModal" @close="cancelFilters" class="w-[calc(100vw-2rem)] min-w-0 max-w-3xl">
        <form wire:submit="loadReport" class="space-y-5" data-test="cost-filters-form">
            <div><flux:heading size="lg">Filtros del costeo</flux:heading><flux:text class="mt-1">Selecciona el producto y el período que deseas consultar.</flux:text></div>
            <div class="grid items-start gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Producto *</flux:label>
                    <flux:button type="button" wire:click="openPicker('products')" icon-trailing="magnifying-glass" class="w-full min-w-0 justify-between" :disabled="! $canSelectProduct" title="{{ $productName }}" data-test="cost-product-picker-button"><span class="truncate">{{ $productName ?: 'Seleccionar producto' }}</span></flux:button>
                    <flux:error name="productId" />
                </flux:field>
                <flux:field>
                    <flux:label>Proveedor</flux:label>
                    <div class="flex min-w-0 gap-2">
                        <flux:button type="button" wire:click="openPicker('providers')" icon-trailing="magnifying-glass" class="min-w-0 flex-1 justify-between" title="{{ $providerName }}" data-test="cost-provider-picker-button"><span class="truncate">{{ $providerName ?: 'Todos los proveedores' }}</span></flux:button>
                        @if ($providerId !== '')<flux:button type="button" wire:click="clearProvider" icon="x-mark" square aria-label="Quitar filtro de proveedor" />@endif
                    </div>
                    <flux:error name="providerId" />
                </flux:field>
                <flux:input wire:model="dateFrom" type="date" label="Desde" />
                <flux:input wire:model="dateTo" type="date" label="Hasta (inclusive)" />
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button type="button" wire:click="clearFilters" icon="arrow-path">Limpiar filtros</flux:button>
                <div class="flex gap-2">
                    <flux:button type="button" wire:click="cancelFilters">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary" icon="chart-bar" wire:loading.attr="disabled" wire:target="loadReport">Consultar costeo</flux:button>
                </div>
            </div>
            <flux:text>Deja las fechas vacías para consultar todo el historial del producto.</flux:text>
            @if ($loadError)<p class="text-sm text-red-600 dark:text-red-400" role="alert">{{ $loadError }}</p>@endif
            @if (! $canSelectProduct)<flux:text>Se necesita el permiso «Buscar productos del reporte de costeo» para usar el selector.</flux:text>@endif
        </form>
        </flux:modal>

        <flux:modal wire:model.self="showPicker" @close="closePicker" class="w-[calc(100vw-2rem)] min-w-0 max-w-4xl">
            <div class="space-y-4" data-test="cost-picker-table">
                <flux:heading size="lg">{{ $pickerKind === 'products' ? 'Seleccionar producto' : 'Filtrar por proveedor' }}</flux:heading>
                <flux:input wire:model.live.debounce.400ms="pickerSearch" icon="magnifying-glass" :label="$pickerKind === 'products' ? 'Buscar producto' : 'Buscar proveedor'" :placeholder="$pickerKind === 'products' ? 'Nombre, código o código de barras…' : 'Nombre del proveedor…'" maxlength="100" />
                <p wire:loading.delay wire:target="openPicker,pickerSearch,pickerGoToPage" class="text-sm text-slate-500" role="status">Buscando…</p>
                @if ($pickerError)<div role="alert" class="flex flex-wrap items-center justify-between gap-3 text-sm text-red-600 dark:text-red-400"><span>{{ $pickerError }}</span><flux:button wire:click="pickerGoToPage({{ $pickerPage }})" size="sm">Reintentar</flux:button></div>@endif
                <div class="farma-table-scroll max-h-[28rem] overflow-auto rounded-lg border border-slate-200 dark:border-slate-700" tabindex="0">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800"><tr>
                            @if ($pickerKind === 'products')<th class="px-4 py-3">Código</th>@endif
                            <th class="px-4 py-3">{{ $pickerKind === 'products' ? 'Producto' : 'Proveedor' }}</th><th class="px-4 py-3 text-right">Acción</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @forelse ($pickerOptions as $option)
                                <tr wire:key="cost-picker-{{ $pickerKind }}-{{ $option['id'] }}">
                                    @if ($pickerKind === 'products')<td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $option['codigo'] ?? '—' }}</td>@endif
                                    <td class="px-4 py-3">{{ $pickerKind === 'products' ? ($option['descripcion'] ?? '') : ($option['nombre'] ?? '') }}</td>
                                    <td class="px-4 py-3 text-right"><flux:button wire:click="selectPickerOption({{ (int) $option['id'] }})" size="sm" variant="primary" aria-label="Seleccionar {{ $pickerKind === 'products' ? ($option['descripcion'] ?? 'producto') : ($option['nombre'] ?? 'proveedor') }}">Seleccionar</flux:button></td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $pickerKind === 'products' ? 3 : 2 }}" class="px-4 py-10 text-center text-slate-500">{{ $pickerError ? 'No fue posible cargar los resultados.' : 'No hay resultados para esta búsqueda.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="text-xs text-slate-500">{{ number_format($pickerTotal) }} resultados · Página {{ $pickerPage }} de {{ $pickerLastPage }}</span>
                    <div class="flex gap-2">
                        <flux:button wire:click="pickerGoToPage({{ $pickerPage - 1 }})" size="sm" :disabled="$pickerPage <= 1" wire:loading.attr="disabled">Anterior</flux:button>
                        <flux:button wire:click="pickerGoToPage({{ $pickerPage + 1 }})" size="sm" :disabled="$pickerPage >= $pickerLastPage" wire:loading.attr="disabled">Siguiente</flux:button>
                        <flux:button wire:click="closePicker" size="sm">Cerrar</flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>

        <div wire:loading.delay wire:target="loadReport" role="status" class="text-sm text-slate-500">Consultando historial de compras…</div>
        @if ($loadError)
            <flux:callout variant="danger" heading="No fue posible consultar el costeo">{{ $loadError }}</flux:callout>
        @elseif ($report === [])
            <div class="rounded-xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
                <flux:icon.chart-bar class="mx-auto size-10 text-cyan-600" />
                <p class="mt-4 font-semibold">Elige un producto para ver su evolución</p>
                <flux:text class="mt-1">Abre Filtros, selecciona un producto y pulsa Consultar costeo.</flux:text>
            </div>
        @else
            @php($summary = $report['resumen'])
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-lg font-semibold">{{ $report['producto']['descripcion'] }}</h2><p class="text-sm text-slate-500">{{ $report['producto']['codigo'] }} · {{ $summary['compras'] }} compras del período</p></div>
                <flux:badge color="cyan">HNL · Por unidad de compra</flux:badge>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-test="cost-summary">
                @foreach (['costo_inicial' => 'Primer costo conocido', 'costo_reciente' => 'Último costo conocido', 'costo_minimo' => 'Costo mínimo'] as $key => $label)
                    <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-xl font-semibold">{{ \App\Services\Reports\ProductCostChart::money($summary[$key]) }}</p><p class="mt-1 text-xs text-slate-500">Antes de ISV y descuentos</p></div>
                @endforeach
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm text-slate-500">Cambio entre primer y último costo</p>
                    <p @class(['mt-2 text-xl font-semibold', 'text-rose-600 dark:text-rose-400' => ($summary['variacion'] ?? 0) > 0, 'text-emerald-600 dark:text-emerald-400' => ($summary['variacion'] ?? 0) < 0])>
                        {{ $summary['variacion'] === null ? 'Sin comparación' : ($summary['variacion'] > 0 ? '+' : '').number_format($summary['variacion'], 4).' HNL' }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">{{ $summary['variacion_pct'] === null ? 'Porcentaje no disponible' : ($summary['variacion_pct'] > 0 ? '+' : '').number_format($summary['variacion_pct'], 2).'%' }}</p>
                </div>
            </div>

            @if ($report['compras'] === [])
                <flux:callout icon="information-circle" heading="Sin compras en el período">Prueba con otras fechas o con todos los proveedores.</flux:callout>
            @elseif ($chart['points'] !== [])
                <section wire:key="cost-chart-{{ hash('sha256', json_encode($report)) }}" x-data="{ selected: {{ count($chart['points']) - 1 }}, points: @js($chart['points']) }" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" data-test="cost-chart">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 p-5 dark:border-slate-800">
                        <div><h3 class="font-semibold">Evolución del costo por compra</h3><p class="mt-1 text-sm text-slate-500">Selecciona un punto para revisar su factura, proveedor e ISV.</p></div>
                        <div class="flex flex-wrap gap-4 text-xs">
                            <span class="flex items-center gap-2"><span class="h-1 w-6 rounded bg-cyan-600"></span>Costo antes de ISV y descuentos</span>
                            <span class="flex items-center gap-2"><span class="h-1 w-6 rounded bg-amber-500"></span>Costo final con ISV y descuentos</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto p-4">
                        <svg viewBox="0 0 960 340" class="min-w-[40rem] w-full" role="img" aria-label="Evolución del costo unitario y costo final por fecha de registro de compra">
                            <text x="84" y="17" font-size="11" class="fill-slate-500 dark:fill-slate-400">HNL por unidad de compra</text>
                            @foreach ($chart['ticks'] as $tick)
                                <line x1="84" y1="{{ $tick['y'] }}" x2="928" y2="{{ $tick['y'] }}" class="stroke-slate-200 dark:stroke-slate-700" />
                                <text x="74" y="{{ $tick['y'] + 4 }}" text-anchor="end" font-size="11" class="fill-slate-500 dark:fill-slate-400">{{ $tick['label'] }}</text>
                            @endforeach
                            @foreach ($chart['labels'] as $label)<text x="{{ $label['x'] }}" y="302" text-anchor="middle" font-size="11" class="fill-slate-500 dark:fill-slate-400">{{ $label['label'] }}</text>@endforeach
                            <text x="516" y="330" text-anchor="middle" font-size="11" class="fill-slate-500 dark:fill-slate-400">Fecha de registro de la compra · De la más antigua a la más reciente</text>
                            @foreach ($chart['series'] as $field => $segments)
                                @foreach ($segments as $segment)<polyline points="{{ $segment }}" fill="none" stroke="{{ $field === 'costo_unitario' ? '#0891b2' : '#f59e0b' }}" stroke-width="2.5" stroke-linejoin="round" @if ($field === 'costo_final_unitario') stroke-dasharray="6 4" @endif />@endforeach
                            @endforeach
                            <line :x1="points[selected]?.x" :x2="points[selected]?.x" y1="28" y2="276" stroke-dasharray="3 4" class="stroke-slate-400 dark:stroke-slate-500" />
                            @foreach ($chart['points'] as $index => $point)
                                <g class="cursor-pointer" @mouseenter="selected = {{ $index }}" @click="selected = {{ $index }}">
                                    <title>{{ $point['invoice'] }} · {{ $point['date'] }} · {{ $point['cost'] }} · ISV: {{ $point['tax'] }}</title>
                                    @if ($point['y'] !== null)<circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" :r="selected === {{ $index }} ? 6 : 3.5" fill="#0891b2" stroke="white" stroke-width="1.5" />@endif
                                    @if ($point['final_y'] !== null)<circle cx="{{ $point['x'] }}" cy="{{ $point['final_y'] }}" :r="selected === {{ $index }} ? 5 : 3" fill="#f59e0b" stroke="white" stroke-width="1.5" />@endif
                                </g>
                            @endforeach
                        </svg>
                    </div>
                    <div class="space-y-4 border-t border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-800/40">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div aria-live="polite"><strong x-text="'Factura ' + points[selected].invoice"></strong><p class="text-sm text-slate-500" x-text="points[selected].date + ' · ' + points[selected].provider"></p></div>
                            <div class="flex gap-2"><flux:button type="button" size="sm" @click="selected--" x-bind:disabled="selected <= 0" aria-label="Compra anterior del gráfico">Anterior</flux:button><flux:button type="button" size="sm" @click="selected++" x-bind:disabled="selected >= points.length - 1" aria-label="Compra siguiente del gráfico">Siguiente</flux:button></div>
                        </div>
                        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach (['cost' => 'Costo antes de ISV', 'final' => 'Costo final', 'tax' => '¿Paga ISV?', 'change' => 'Cambio frente a compra anterior'] as $field => $label)
                                <div><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 font-semibold" x-text="points[selected].{{ $field }}"></dd></div>
                            @endforeach
                        </dl>
                    </div>
                </section>
            @else
                <flux:callout heading="No hay costos completos para graficar">Revisa el detalle de las compras y las bonificaciones del producto.</flux:callout>
            @endif

            @if ($report['compras'] !== [])
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <div class="p-5"><h3 class="font-semibold">Detalle de compras</h3><p class="mt-1 text-sm text-slate-500">De la más reciente a la más antigua. La variación compara el costo antes de ISV con la compra anterior del período.</p></div>
                    <div class="farma-table-scroll overflow-x-auto" tabindex="0"><table class="min-w-full text-sm" data-test="cost-purchases-table">
                        <thead class="border-y border-slate-200 bg-slate-50 text-left text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-800/40"><tr>
                            @foreach (['Registro / factura', 'Proveedor / lotes', 'Cantidad / bonificación', 'Costo sin ISV', 'Descuento por unidad', '¿Paga ISV?', 'ISV por unidad', 'Costo final', 'Cambio'] as $heading)<th class="whitespace-nowrap px-4 py-3">{{ $heading }}</th>@endforeach
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($rows as $row)
                                <tr wire:key="cost-purchase-{{ $row['id'] }}">
                                    <td class="whitespace-nowrap px-4 py-4"><strong>{{ $row['factura'] }}</strong><p class="mt-1 text-xs text-slate-500">{{ empty($row['fecha']) ? 'Sin fecha' : \Carbon\Carbon::parse($row['fecha'])->format('d/m/Y H:i') }}</p></td>
                                    <td class="px-4 py-4"><span>{{ $row['proveedor'] ?? 'Sin proveedor' }}</span><p class="mt-1 text-xs text-slate-500">Lotes: {{ implode(', ', $row['lotes']) }}</p></td>
                                    <td class="whitespace-nowrap px-4 py-4">{{ number_format($row['cantidad']) }}<p class="mt-1 text-xs text-slate-500">{{ number_format($row['bonificaciones']) }} bonificadas</p>@if ($row['solo_bonificacion'])<flux:badge color="amber" size="sm">Solo bonificación</flux:badge>@endif</td>
                                    <td class="whitespace-nowrap px-4 py-4 font-semibold">{{ \App\Services\Reports\ProductCostChart::money($row['costo_unitario']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-4">{{ \App\Services\Reports\ProductCostChart::money($row['descuento_unitario']) }}</td>
                                    <td class="px-4 py-4"><flux:badge :color="match ($row['isv_estado']) {'si' => 'amber', 'no' => 'zinc', 'mixto' => 'purple', default => 'zinc'}">{{ \App\Services\Reports\ProductCostChart::taxLabel($row['isv_estado']) }}</flux:badge></td>
                                    <td class="whitespace-nowrap px-4 py-4">{{ \App\Services\Reports\ProductCostChart::money($row['isv_unitario']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-4 font-semibold">{{ \App\Services\Reports\ProductCostChart::money($row['costo_final_unitario']) }}</td>
                                    <td @class(['whitespace-nowrap px-4 py-4', 'text-rose-600 dark:text-rose-400' => $row['tendencia'] === 'sube', 'text-emerald-600 dark:text-emerald-400' => $row['tendencia'] === 'baja'])>
                                        {{ match ($row['tendencia']) {'sube' => '↑ Aumentó', 'baja' => '↓ Disminuyó', 'igual' => 'Sin cambio', default => 'Sin comparación'} }}
                                        @if ($row['variacion'] !== null)<p class="mt-1 text-xs">{{ $row['variacion'] > 0 ? '+' : '' }}{{ number_format($row['variacion'], 4) }} HNL @if ($row['variacion_pct'] !== null)· {{ $row['variacion_pct'] > 0 ? '+' : '' }}{{ number_format($row['variacion_pct'], 2) }}%@endif</p>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                    <div class="flex items-center justify-between gap-3 border-t border-slate-200 p-4 dark:border-slate-800"><flux:text>Página {{ $page }} de {{ $lastPage }}</flux:text><div class="flex gap-2"><flux:button wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Anterior</flux:button><flux:button wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Siguiente</flux:button></div></div>
                </section>
            @endif
            <div class="space-y-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                <p>Un punto por compra. Cuando hay varios lotes, se usa el promedio ponderado por cantidad. El costo final incluye el ISV y resta los descuentos registrados.</p>
                <p>Las bonificaciones se muestran por separado y no reducen artificialmente el costo comparado. Los borradores y las compras anuladas o eliminadas se excluyen.</p>
                <p>El ISV se obtiene de los importes guardados, sin aplicar la tasa actual a compras anteriores. Los importes faltantes se muestran como «Sin dato». La comparación corresponde al mismo producto, sin convertir presentaciones.</p>
            </div>
        @endif
    </div>
</div>
