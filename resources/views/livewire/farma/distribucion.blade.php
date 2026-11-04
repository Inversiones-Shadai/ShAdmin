<div class="flex min-h-0 flex-1 flex-col gap-6" data-test="distribution-screen">
    <header class="shrink-0">
        <div class="mb-2 flex items-center gap-2 text-sm font-medium text-cyan-700 dark:text-cyan-300">
            <flux:icon.currency-dollar class="size-4" />Farma · Productos
        </div>
        <flux:heading size="xl">Distribución</flux:heading>
        <flux:text class="mt-2 max-w-3xl">Establece el precio y los descuentos de cada lote. El costo y el ISV se heredan de la compra.</flux:text>
    </header>

    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex shrink-0 flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div>
                <h2 class="font-semibold text-slate-900 dark:text-white">Productos con lotes</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ number_format($total) }} productos encontrados</p>
            </div>
            <flux:input wire:model.live.debounce.500ms="search" icon="magnifying-glass" placeholder="Buscar producto, código o laboratorio…" class="w-full sm:max-w-sm" />
        </div>

        @if ($loadError)
            <div class="m-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $loadError }}</div>
        @elseif ($products === [])
            <div class="grid min-h-56 place-items-center p-8 text-center">
                <div><span class="mx-auto grid size-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800"><flux:icon.archive-box class="size-6" /></span><p class="mt-3 font-medium">No hay productos con lotes</p><p class="mt-1 text-sm text-slate-500">Los productos aparecerán cuando una compra sea enviada al Kardex.</p></div>
            </div>
        @else
            <div class="min-h-0 flex-1 overflow-auto overscroll-contain" tabindex="0" role="region" aria-label="Productos con lotes">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="sticky top-0 z-10 bg-white dark:bg-slate-900">
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Código</th>
                            <th class="min-w-72 px-4 py-3">Producto</th>
                            <th class="min-w-48 px-4 py-3">Laboratorio</th>
                            <th class="px-4 py-3 text-right">Lotes</th>
                            <th class="px-4 py-3 text-right">Cantidad</th>
                            <th class="whitespace-nowrap px-4 py-3">Configuración</th>
                            <th class="whitespace-nowrap px-4 py-3">Próxima expiración</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($products as $product)
                            <tr wire:key="distribution-product-{{ $product['producto_id'] }}">
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold">{{ $product['codigo'] ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="grid h-16 w-20 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-50 dark:bg-slate-800/70">
                                            @if ($access['image'] && ! empty($product['tiene_imagen']))
                                                <img data-session-image-src="{{ ($product['imagen_url'] ?? route('farma.products.preview', ['product' => $product['producto_id']])) }}" alt="Vista previa de {{ $product['descripcion'] }}" class="h-16 w-full object-contain p-1" loading="lazy" />
                                                <flux:icon.photo data-session-image-placeholder class="hidden size-8 text-slate-300 dark:text-slate-600" />
                                            @else
                                                <flux:icon.photo class="size-8 text-slate-300 dark:text-slate-600" />
                                            @endif
                                        </div>
                                        <span class="flex min-w-0 flex-wrap items-center gap-x-1 font-semibold text-slate-900 dark:text-white">
                                            <span>{{ $product['descripcion'] }}</span>
                                            @if (! empty($product['presentacion']))<span class="font-normal text-slate-500 dark:text-slate-400">· {{ $product['presentacion'] }}</span>@endif
                                            @if (! empty($product['administracion']))<span class="font-normal text-slate-500 dark:text-slate-400">· {{ $product['administracion'] }}</span>@endif
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ $product['laboratorio'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-right"><flux:badge color="blue">{{ number_format((int) $product['lotes_count']) }}</flux:badge></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold">{{ number_format((int) $product['cantidad_total']) }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @php($configured = (int) ($product['lotes_configurados'] ?? 0))
                                    <flux:badge :color="$configured === (int) $product['lotes_count'] ? 'green' : 'amber'">{{ $configured }}/{{ (int) $product['lotes_count'] }} lotes</flux:badge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">{{ ! empty($product['proxima_expiracion']) ? Illuminate\Support\Carbon::parse($product['proxima_expiracion'])->format('d/m/Y') : '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($access['detail'])
                                        <flux:button wire:click="openPricing({{ $product['producto_id'] }})" size="sm" variant="ghost" icon="currency-dollar" title="Establecer precios" aria-label="Establecer precios de {{ $product['descripcion'] }}" data-test="set-product-pricing" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex shrink-0 items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800">
                <span class="text-sm text-slate-500">Página {{ $page }} de {{ $lastPage }}</span>
                <div class="flex gap-2"><flux:button wire:click="previousPage" size="sm" :disabled="$page <= 1">Anterior</flux:button><flux:button wire:click="nextPage" size="sm" :disabled="$page >= $lastPage">Siguiente</flux:button></div>
            </div>
        @endif
    </section>

    <flux:modal wire:model.self="showPricingModal" class="w-[calc(100vw-2rem)] max-w-5xl bg-white! dark:bg-slate-900!">
        <form wire:submit="savePricing" class="space-y-5" data-test="distribution-pricing-modal">
            <div class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-cyan-50 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-300"><flux:icon.currency-dollar class="size-6" /></span>
                <div><flux:heading size="lg">Precios de {{ $selectedProduct['descripcion'] ?? 'producto' }}</flux:heading><flux:text class="mt-1">Código {{ $selectedProduct['codigo'] ?? '—' }} · {{ $selectedProduct['laboratorio'] ?? 'Sin laboratorio' }}</flux:text></div>
            </div>

            <x-remote-select source="distribution-lots" cache="lots" :filters="['product' => $selectedProduct['producto_id'] ?? null]" wire:model.live="selectedLot" label="Lote" placeholder="Selecciona un lote" data-test="distribution-lot-selector" />
            @error('selectedLot')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            @if (is_array($activeLot))
                <div class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2 lg:grid-cols-6 dark:border-slate-700 dark:bg-slate-800/50">
                    <div><span class="block text-xs uppercase text-slate-500">Cantidad</span><strong>{{ number_format((int) ($activeLot['cantidad'] ?? 0)) }}</strong></div>
                    <div><span class="block text-xs uppercase text-slate-500">Elaboración</span><strong>{{ ! empty($activeLot['fecha_elaboracion']) ? Illuminate\Support\Carbon::parse($activeLot['fecha_elaboracion'])->format('d/m/Y') : '—' }}</strong></div>
                    <div><span class="block text-xs uppercase text-slate-500">Expiración</span><strong>{{ ! empty($activeLot['fecha_expiracion']) ? Illuminate\Support\Carbon::parse($activeLot['fecha_expiracion'])->format('d/m/Y') : '—' }}</strong></div>
                    <div><span class="block text-xs uppercase text-slate-500">Factura</span><strong>{{ data_get($activeLot, 'factura.nro', 'Sin referencia') }}</strong></div>
                    <div><span class="block text-xs uppercase text-slate-500">Costo unitario</span><strong data-test="distribution-unit-cost">{{ $unitCostWithTax !== null ? 'L '.number_format($unitCostWithTax, 2) : 'No disponible' }}</strong></div>
                    <div><span class="block text-xs uppercase text-slate-500">ISV de compra</span><strong>{{ $activeLot['isv'] === null ? 'Sin información' : ($activeLot['isv'] ? 'Sí, '.number_format((float) config('farma.purchase_isv_rate', 0.15) * 100, 0).'%' : 'No') }}</strong></div>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model.live.debounce.1000ms="precio" type="number" min="0.01" max="99999999.99" step="0.01" label="Precio" prefix="L" />
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
                    <span class="block text-xs font-medium uppercase text-slate-500">Condición tributaria heredada</span>
                    <div class="mt-2 flex items-center gap-2">
                        @if (is_array($activeLot) && $activeLot['isv'] !== null)
                            <flux:badge :color="$activeLot['isv'] ? 'amber' : 'zinc'">{{ $activeLot['isv'] ? 'Gravado con ISV' : 'Exento de ISV' }}</flux:badge>
                        @else
                            <flux:badge color="zinc">ISV sin información</flux:badge>
                        @endif
                        <span class="text-xs text-slate-500">No se modifica en Distribución.</span>
                    </div>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['dto1' => 'Descuento 1', 'dto2' => 'Descuento 2', 'dto3' => 'Descuento 3', 'dto4' => 'Descuento 4'] as $field => $label)
                    @php($reference = $discountReferences[$field])
                    <div class="space-y-2" wire:key="distribution-reference-{{ $field }}">
                        <flux:field>
                            <flux:label class="gap-2">
                                {{ $label }}
                                @if ($reference['ganancia'] === null)
                                    <span class="text-xs text-slate-400" title="Costo no disponible" data-test="{{ $field }}-gain">—</span>
                                @else
                                    <strong title="Ganancia" data-test="{{ $field }}-gain" @class([
                                        'text-emerald-600 dark:text-emerald-400' => $reference['ganancia'] > 0,
                                        'text-red-600 dark:text-red-400' => $reference['ganancia'] < 0,
                                        'text-slate-600 dark:text-slate-300' => $reference['ganancia'] === 0.0,
                                    ])><span class="sr-only">Ganancia: </span>{{ $reference['ganancia'] > 0 ? '+' : '' }}{{ number_format($reference['ganancia'], 2) }}%</strong>
                                @endif
                            </flux:label>
                            <flux:input wire:model.live.debounce.1000ms="{{ $field }}" type="number" min="0" max="99.99" step="0.01" suffix="%" />
                            <flux:error :name="$field" />
                        </flux:field>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-800/50" data-test="{{ $field }}-reference">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-500 dark:text-slate-400">Subtotal</span>
                                <span>L {{ number_format($reference['subtotal'], 2) }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between gap-2">
                                <span class="text-slate-500 dark:text-slate-400">Descuento</span>
                                <span>L {{ number_format($reference['descuento'], 2) }}</span>
                            </div>
                            @if ($reference['isv'] !== null)
                                <div class="mt-1 flex items-center justify-between gap-2" data-test="{{ $field }}-isv">
                                    <span class="text-slate-500 dark:text-slate-400">ISV</span>
                                    <span>L {{ number_format($reference['isv'], 2) }}</span>
                                </div>
                            @endif
                            <div class="mt-2 flex items-center justify-between gap-2 border-t border-slate-200 pt-2 dark:border-slate-700">
                                <span class="text-slate-500 dark:text-slate-400">Total</span>
                                <strong class="text-slate-900 dark:text-white">L {{ number_format($reference['total'], 2) }}</strong>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Referencia: el total resta el descuento al subtotal y agrega el ISV cuando corresponde. El costo unitario incluye el ISV de compra y se utiliza para calcular el porcentaje de ganancia.</p>

            <section class="space-y-4 rounded-xl border border-cyan-200 bg-cyan-50/40 p-4 dark:border-cyan-900 dark:bg-cyan-950/20" data-test="lot-promotion">
                <flux:heading>Promoción del lote</flux:heading>
                <flux:checkbox wire:model.live="promocion_activa" label="Activar promoción" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="promocion_porcentaje" type="number" min="0.01" max="99.99" step="0.01" suffix="%" label="Descuento de promoción" />
                    <flux:input wire:model="promocion_hasta" type="date" label="Válida hasta (inclusive)" />
                </div>
                <flux:text>Mientras esté vigente, POS aplicará la promoción en este lote con prioridad sobre los demás descuentos, incluidos los de 3ra y 4ta edad.</flux:text>
                <flux:checkbox wire:model.live="promocion_todos_stocks" label="Disponible en todos los stocks" />
                @if (! $promocion_todos_stocks)
                    <div class="space-y-3" data-test="promotion-stocks">
                        <div class="flex items-end gap-2">
                            <div class="min-w-0 flex-1">
                                <x-remote-select source="distribution-stocks" cache="promotionStocks" wire:model="promotionStock" label="Stock disponible" placeholder="Selecciona un stock" />
                            </div>
                            <flux:button type="button" wire:click="addPromotionStock" icon="plus">Agregar stock</flux:button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($promocion_stock_ids as $stockId)
                                <div class="flex items-center gap-2 rounded-lg border border-cyan-300 px-3 py-1.5 text-sm dark:border-cyan-800" wire:key="promotion-stock-{{ $stockId }}">
                                    <span>{{ $promotionStockLabels[$stockId] ?? 'Stock '.$stockId }}</span>
                                    <flux:button type="button" wire:click="removePromotionStock({{ (int) $stockId }})" size="xs" variant="ghost" icon="x-mark" aria-label="Quitar stock de la promoción" />
                                </div>
                            @endforeach
                        </div>
                        <flux:error name="promocion_stock_ids" />
                        <flux:text>El descuento y el encabezado promocional estarán disponibles únicamente en estos stocks.</flux:text>
                    </div>
                @endif
                <flux:checkbox wire:model="mostrar_encabezado" label="Mostrar encabezado en la tarjeta de producto" />
                <flux:input wire:model="encabezado_texto" maxlength="160" label="Texto del encabezado (una vez por lote)" placeholder="Producto promocional" />
                <flux:text>El encabezado se muestra únicamente mientras la promoción esté vigente. Si no escribes un texto, se mostrará «Producto promocional».</flux:text>
            </section>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closePricing">Cerrar</flux:button>
                @if ($access['save'])<flux:button type="submit" variant="primary" icon="check" :disabled="$selectedLot === ''">Guardar precios</flux:button>@endif
            </div>
        </form>
    </flux:modal>
</div>
