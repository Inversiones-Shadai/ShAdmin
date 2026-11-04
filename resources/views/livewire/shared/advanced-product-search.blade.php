<div data-test="advanced-product-search">
    <style>
        .advanced-product-search-modal {
            width: calc(100vw - 2rem) !important;
            max-width: calc(100vw - 2rem) !important;
        }

        @media (min-width: 64rem) {
            dialog.advanced-product-search-content-modal {
                inset-inline-start: 17rem !important;
            }

            .advanced-product-search-modal {
                width: calc(100vw - 19rem) !important;
                max-width: calc(100vw - 19rem) !important;
            }
        }
    </style>

    <flux:modal
        wire:model.self="showModal"
        class="advanced-product-search-content-modal advanced-product-search-modal bg-white! dark:bg-slate-900!"
    >
        <div class="space-y-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <flux:heading size="lg">{{ $productsWithLots ? 'Productos con lotes' : 'Búsqueda avanzada de productos' }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $productsWithLots ? 'Busca un producto registrado en lotes por nombre, código o laboratorio.' : 'Busca por nombre, código o código de barras y aplica filtros opcionales.' }}
                    </flux:text>
                </div>
                <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ number_format($total) }} coincidencias
                </span>
            </div>

            <div class="grid gap-3 {{ $productsWithLots ? 'lg:grid-cols-[1fr_auto]' : 'lg:grid-cols-[minmax(18rem,2fr)_minmax(12rem,1fr)_auto]' }} lg:items-end">
                <flux:input
                    wire:model.live.debounce.350ms="searchTerm"
                    label="Producto"
                    icon="magnifying-glass"
                    placeholder="{{ $productsWithLots ? 'Nombre, código o laboratorio…' : 'Nombre, código o código de barras…' }}"
                    autofocus
                />
                @if (! $productsWithLots)
                    <x-remote-select source="product-laboratories" cache="laboratories" wire:model.live="laboratory" label="Laboratorio" />
                @endif
                <flux:button type="button" wire:click="clearFilters" variant="ghost" icon="x-mark">
                    Limpiar
                </flux:button>
            </div>

            <div class="relative min-h-80">
                <div
                    wire:loading.flex
                    wire:target="searchTerm,laboratory,clearFilters,previousPage,nextPage"
                    class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/75 backdrop-blur-[1px] dark:bg-slate-900/75"
                >
                    <div class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
                        <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                        </svg>
                        Buscando productos…
                    </div>
                </div>

                @if ($loadError)
                    <div class="grid min-h-80 place-items-center rounded-xl border border-red-200 bg-red-50 p-8 text-center dark:border-red-900/60 dark:bg-red-950/20">
                        <div>
                            <p class="font-semibold text-red-700 dark:text-red-300">No fue posible completar la búsqueda</p>
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $loadError }}</p>
                        </div>
                    </div>
                @elseif ($products === [])
                    <div class="grid min-h-80 place-items-center rounded-xl border border-dashed border-slate-300 p-8 text-center dark:border-slate-700">
                        <div class="max-w-sm">
                            <flux:icon.magnifying-glass class="mx-auto size-9 text-slate-400" />
                            <p class="mt-3 font-semibold text-slate-700 dark:text-slate-200">No encontramos productos</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Prueba con otro término o elimina alguno de los filtros.</p>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5" data-test="advanced-product-results">
                        @foreach ($products as $product)
                            @php($excluded = in_array($product['id'], $excludedIds, true))
                            <article
                                wire:key="advanced-product-{{ $product['id'] }}"
                                class="flex min-h-64 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
                                data-test="advanced-product-card"
                            >
                                <div class="grid h-24 place-items-center overflow-hidden rounded-lg bg-slate-50 dark:bg-slate-800/70">
                                    @if ($canViewImages && $product['tiene_imagen'])
                                        <img
                                            data-session-image-src="{{ $product['imagen_url'] ?? route('farma.products.preview', ['product' => $product['id']]) }}"
                                            alt="Vista previa de {{ $product['descripcion'] }}"
                                            class="h-24 w-full object-contain p-1"
                                            loading="lazy"
                                        />
                                    @else
                                        <flux:icon.photo class="size-9 text-slate-300 dark:text-slate-600" />
                                    @endif
                                </div>

                                <div class="mt-3 flex flex-1 flex-col">
                                    <h3 class="line-clamp-2 min-h-10 text-sm font-semibold leading-5 text-slate-900 dark:text-white" title="{{ $product['descripcion'] }}">
                                        {{ $product['descripcion'] }}
                                    </h3>
                                    <p class="mt-1 line-clamp-1 text-xs font-medium text-emerald-700 dark:text-emerald-400" title="{{ $product['laboratorio'] }}">
                                        {{ $product['laboratorio'] }}
                                    </p>
                                    <div class="mt-2 space-y-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                        <p class="truncate"><span class="font-medium">Código:</span> {{ $product['codigo'] ?: '—' }}</p>
                                        <p class="truncate"><span class="font-medium">Barras:</span> {{ $product['codigobar'] ?: '—' }}</p>
                                    </div>

                                    <flux:button
                                        type="button"
                                        wire:click="selectProduct({{ $product['id'] }})"
                                        variant="{{ $excluded ? 'ghost' : 'primary' }}"
                                        size="sm"
                                        class="mt-3 w-full"
                                        :disabled="$excluded"
                                    >
                                        {{ $excluded ? 'Ya agregado' : 'Seleccionar' }}
                                    </flux:button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Página {{ $page }} de {{ $lastPage }}
                    @if (! $productsWithLots)
                        · hasta 10 productos por página
                    @endif
                </p>
                <div class="flex justify-end gap-2">
                    @if ($lastPage > 1)
                        <flux:button type="button" wire:click="previousPage" icon="chevron-left" :disabled="$page <= 1">
                            Anterior
                        </flux:button>
                        <flux:button type="button" wire:click="nextPage" icon-trailing="chevron-right" :disabled="$page >= $lastPage">
                            Siguiente
                        </flux:button>
                    @endif
                    <flux:button type="button" wire:click="close">Cerrar</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
