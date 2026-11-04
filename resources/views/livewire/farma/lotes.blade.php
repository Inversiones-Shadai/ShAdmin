<div class="farma-screen" data-test="lots-screen">
    <style>
        .lots-detail-modal {
            width: calc(100vw - 2rem) !important;
            max-width: calc(100vw - 2rem) !important;
        }

        @media (min-width: 64rem) {
            dialog.lots-content-modal {
                inset-inline-start: 17rem !important;
            }

            .lots-detail-modal {
                width: calc(100vw - 19rem) !important;
                max-width: calc(100vw - 19rem) !important;
            }
        }
    </style>

    <header>
        <div class="mb-2 flex items-center gap-2 text-sm font-medium text-cyan-700 dark:text-cyan-300"><flux:icon.archive-box class="size-4" />Farma · Productos</div>
        <flux:heading size="xl">Lotes</flux:heading>
        <flux:text class="mt-2 max-w-2xl">Consulta los productos que tienen lotes registrados y revisa su procedencia por factura.</flux:text>
    </header>

    <section class="farma-table-panel overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div><h2 class="font-semibold text-slate-900 dark:text-white">Productos con lotes</h2><p class="text-sm text-slate-500 dark:text-slate-400">{{ number_format($total) }} productos encontrados</p></div>
            <flux:input wire:model.live.debounce.500ms="search" icon="magnifying-glass" placeholder="Buscar producto, código o laboratorio…" class="w-full sm:max-w-sm" />
        </div>

        @if ($loadError)
            <div class="m-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $loadError }}</div>
        @elseif ($products === [])
            <div class="grid min-h-56 place-items-center p-8 text-center"><div><span class="mx-auto grid size-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800"><flux:icon.archive-box class="size-6" /></span><p class="mt-3 font-medium">No hay productos con lotes</p><p class="mt-1 text-sm text-slate-500">Los productos aparecerán cuando una compra sea enviada al Kardex.</p></div></div>
        @else
            <div class="farma-table-scroll overflow-x-auto" tabindex="0">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead><tr class="text-left text-xs uppercase tracking-wide text-slate-500"><th class="px-4 py-3">Código</th><th class="min-w-72 px-4 py-3">Producto</th><th class="min-w-48 px-4 py-3">Laboratorio</th><th class="px-4 py-3 text-right">Lotes</th><th class="px-4 py-3 text-right">Cantidad total</th><th class="whitespace-nowrap px-4 py-3">Próxima expiración</th><th class="px-4 py-3 text-right">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($products as $product)
                            <tr wire:key="lot-product-{{ $product['producto_id'] }}">
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold">{{ $product['codigo'] ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="grid h-16 w-20 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-50 dark:bg-slate-800/70">
                                            @if ($access['image'] && ! empty($product['tiene_imagen']))
                                                <img
                                                    data-session-image-src="{{ ($product['imagen_url'] ?? route('farma.products.preview', ['product' => $product['producto_id']])) }}"
                                                    alt="Vista previa de {{ $product['descripcion'] }}"
                                                    class="h-16 w-full object-contain p-1"
                                                    loading="lazy"
                                                />
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
                                <td class="whitespace-nowrap px-4 py-3">{{ ! empty($product['proxima_expiracion']) ? Illuminate\Support\Carbon::parse($product['proxima_expiracion'])->format('d/m/Y') : '—' }}</td>
                                <td class="px-4 py-3 text-right">@if ($access['detail'])<flux:button wire:click="openLots({{ $product['producto_id'] }})" size="sm" variant="ghost" icon="list-bullet" title="Ver lotes" aria-label="Ver lotes de {{ $product['descripcion'] }}" data-test="view-product-lots" />@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800"><span class="text-sm text-slate-500">Página {{ $page }} de {{ $lastPage }}</span><div class="flex gap-2"><flux:button wire:click="previousPage" size="sm" :disabled="$page <= 1">Anterior</flux:button><flux:button wire:click="nextPage" size="sm" :disabled="$page >= $lastPage">Siguiente</flux:button></div></div>
        @endif
    </section>

    <flux:modal wire:model.self="showLotsModal" class="lots-content-modal lots-detail-modal bg-white! dark:bg-slate-900!">
        <div class="space-y-5" data-test="product-lots-modal">
            <div class="flex items-start gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-xl bg-cyan-50 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-300"><flux:icon.archive-box class="size-6" /></span><div><flux:heading size="lg">Lotes de {{ $selectedProduct['descripcion'] ?? 'producto' }}</flux:heading><flux:text class="mt-1">Código {{ $selectedProduct['codigo'] ?? '—' }} · {{ $selectedProduct['laboratorio'] ?? 'Sin laboratorio' }}</flux:text></div></div>
            <div class="farma-table-scroll overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700" tabindex="0">
                <table class="w-full min-w-6xl table-fixed text-sm">
                    <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="w-[14%] px-4 py-3">Lote</th><th class="w-[17%] whitespace-nowrap px-4 py-3">Fecha de elaboración</th><th class="w-[17%] whitespace-nowrap px-4 py-3">Fecha de expiración</th><th class="w-[10%] px-4 py-3 text-right">Cantidad</th><th class="w-[12%] px-4 py-3 text-right">Costo</th><th class="w-[10%] px-4 py-3 text-center">ISV</th><th class="w-[20%] whitespace-nowrap px-4 py-3">Número de factura</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($lots as $lot)
                            <tr wire:key="lot-{{ $lot['id'] }}"><td class="break-all px-4 py-3 font-semibold">{{ $lot['lote'] }}</td><td class="whitespace-nowrap px-4 py-3">{{ ! empty($lot['fecha_elaboracion']) ? Illuminate\Support\Carbon::parse($lot['fecha_elaboracion'])->format('d/m/Y') : '—' }}</td><td class="whitespace-nowrap px-4 py-3">{{ ! empty($lot['fecha_expiracion']) ? Illuminate\Support\Carbon::parse($lot['fecha_expiracion'])->format('d/m/Y') : '—' }}</td><td class="px-4 py-3 text-right font-semibold">{{ number_format((int) $lot['cantidad']) }}</td><td class="whitespace-nowrap px-4 py-3 text-right font-semibold">{{ $lot['costo'] !== null ? 'L '.number_format((float) $lot['costo'], 2) : '—' }}</td><td class="px-4 py-3 text-center">@if ($lot['isv'] === null)<span class="text-slate-400">—</span>@else<flux:badge :color="$lot['isv'] ? 'amber' : 'zinc'">{{ $lot['isv'] ? 'Sí' : 'No' }}</flux:badge>@endif</td><td class="break-all px-4 py-3 font-medium">{{ data_get($lot, 'factura.nro', 'Sin referencia') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end"><flux:button wire:click="closeLots">Cerrar</flux:button></div>
        </div>
    </flux:modal>
</div>
