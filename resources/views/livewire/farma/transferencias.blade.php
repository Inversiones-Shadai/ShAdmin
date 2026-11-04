<div class="farma-screen" data-test="transfers-screen">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="mb-2 flex items-center gap-2 text-sm font-medium text-cyan-700 dark:text-cyan-300">
                <flux:icon.arrows-right-left class="size-4" />Farma · Inventario
            </div>
            <flux:heading size="xl">Transferencias</flux:heading>
            <flux:text class="mt-2 max-w-3xl">Prepara los lotes y cantidades, revisa costos e ISV y envía la transferencia al stock de destino.</flux:text>
        </div>
        @if ($access['send'])
            <flux:button wire:click="openSend" variant="primary" icon="plus" data-test="create-transfer">
                Nueva transferencia
            </flux:button>
        @endif
    </header>

    <section class="farma-table-panel overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 2xl:flex-row 2xl:items-center 2xl:justify-between dark:border-slate-800">
            <div>
                <h2 class="font-semibold text-slate-900 dark:text-white">Historial de transferencias</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ number_format($total) }} transferencias encontradas</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Fecha de envío: {{ $dateSummary }}</p>
            </div>
            <flux:button wire:click="openFilters" icon="adjustments-horizontal" data-test="transfer-filters-button">Filtros</flux:button>
        </div>

        @if ($loadError)
            <div class="m-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $loadError }}</div>
        @elseif ($transfers === [])
            <div class="grid min-h-56 place-items-center p-8 text-center">
                <div>
                    <span class="mx-auto grid size-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                        <flux:icon.arrows-right-left class="size-6" />
                    </span>
                    <p class="mt-3 font-medium">No hay transferencias</p>
                    <p class="mt-1 text-sm text-slate-500">Aquí aparecerán los envíos entre stocks.</p>
                </div>
            </div>
        @else
            <div class="farma-table-scroll overflow-x-auto" tabindex="0">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">N.º</th>
                            <th class="min-w-44 px-4 py-3">Origen</th>
                            <th class="min-w-44 px-4 py-3">Destino</th>
                            <th class="px-4 py-3">Envío</th>
                            <th class="px-4 py-3">Recepción</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($transfers as $transfer)
                            @php
                                $id = (int) ($transfer['id'] ?? 0);
                                $origin = data_get($transfer, 'stock_de.descripcion', data_get($transfer, 'stock_de.nombre', $transfer['stock_de'] ?? '—'));
                                $destination = data_get($transfer, 'stock_para.descripcion', data_get($transfer, 'stock_para.nombre', $transfer['stock_para'] ?? '—'));
                                $sentStatus = (string) ($transfer['estado_label'] ?? $transfer['estado'] ?? 'ENVIADA');
                                $receiptStatus = (string) ($transfer['estado_recepcion_label'] ?? $transfer['estado_recepcion'] ?? 'PENDIENTE');
                                $receiptPending = Illuminate\Support\Str::upper($receiptStatus) === 'PENDIENTE';
                                $canReceive = (bool) ($transfer['puede_recibir'] ?? false);
                                $sentAt = $transfer['enviado_at'] ?? $transfer['created_at'] ?? null;
                                $receivedAt = $transfer['recibido_at'] ?? null;
                            @endphp
                            <tr wire:key="transfer-{{ $id }}">
                                <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $transfer['nro'] ?? $transfer['numero'] ?? '#'.$id }}</td>
                                <td class="px-4 py-3">{{ $origin }}</td>
                                <td class="px-4 py-3">{{ $destination }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="block">{{ $sentAt ? Illuminate\Support\Carbon::parse($sentAt)->format('d/m/Y H:i') : '—' }}</span>
                                    <span class="block text-xs text-slate-500">{{ data_get($transfer, 'usuario_envia.name', data_get($transfer, 'usuario_envia.nombre', '')) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="block">{{ $receivedAt ? Illuminate\Support\Carbon::parse($receivedAt)->format('d/m/Y H:i') : 'Pendiente' }}</span>
                                    <span class="block text-xs text-slate-500">{{ data_get($transfer, 'usuario_recibe.name', data_get($transfer, 'usuario_recibe.nombre', '')) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex flex-col items-start gap-1">
                                        <flux:badge color="blue">{{ $sentStatus }}</flux:badge>
                                        <flux:badge :color="$receiptPending ? 'amber' : 'green'">{{ $receiptStatus }}</flux:badge>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <div class="flex justify-end gap-1">
                                        @if ($access['search'])
                                            <flux:button wire:click="openDetail({{ $id }})" size="sm" variant="ghost" icon="eye" title="Ver detalle" aria-label="Ver detalle de transferencia" />
                                            @if (($transfer['puede_imprimir'] ?? false) === true)
                                                @include('livewire.farma.partials.transfer-print-menu', ['printId' => $id])
                                            @endif
                                        @endif
                                        @if ($access['receive'] && $canReceive)
                                            <flux:button wire:click="confirmReceive({{ $id }})" size="sm" variant="ghost" icon="inbox-arrow-down" title="Confirmar recepción" aria-label="Recibir transferencia" data-test="receive-transfer" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800">
                <span class="text-sm text-slate-500">Página {{ $page }} de {{ $lastPage }}</span>
                <div class="flex gap-2">
                    <flux:button wire:click="previousPage" size="sm" :disabled="$page <= 1">Anterior</flux:button>
                    <flux:button wire:click="nextPage" size="sm" :disabled="$page >= $lastPage">Siguiente</flux:button>
                </div>
            </div>
        @endif
    </section>

    <flux:modal wire:model.self="showFiltersModal" @close="cancelFilters" class="w-[calc(100vw-2rem)] min-w-0 max-w-3xl">
        <form wire:submit="applyFilters" class="space-y-5" data-test="transfer-filters-form">
            <div>
                <flux:heading size="lg">Filtros de transferencias</flux:heading>
                <flux:text class="mt-1">Consulta por fecha de envío, stock, dirección, tipo o búsqueda.</flux:text>
            </div>
            <div class="grid items-start gap-4 sm:grid-cols-2">
                <flux:input wire:model="dateFrom" type="date" label="Desde" />
                <flux:input wire:model="dateTo" type="date" label="Hasta (inclusive)" />
                @if ($access['filterStocks'])
                    <x-remote-select source="transfer-filter-stocks" placeholder="Stock de esta sesión" cache="transferStocks" wire:model.live="stockFilter" label="Stock" data-test="transfer-stock-filter" />
                @endif
                <flux:select wire:model.live="direction" label="Mostrar transferencias" data-test="transfer-direction-filter">
                    <option value="todas">Todas</option>
                    <option value="enviadas">Enviadas</option>
                    <option value="recibidas">Recibidas</option>
                </flux:select>
                <x-remote-select source="transfer-filter-types" placeholder="Todos los tipos" cache="transferTypes" wire:model.live="typeFilter" label="Tipo de transferencia" data-test="transfer-type-filter" />
                <flux:input wire:model="search" icon="magnifying-glass" label="Buscar" placeholder="Número, stock o usuario…" maxlength="100" />
            </div>
            <flux:text>Al abrir esta vista se muestra el día actual de Honduras. Deja ambas fechas vacías para consultar todo el historial.</flux:text>
            @if ($loadError)<p role="alert" class="text-sm text-red-600 dark:text-red-400">{{ $loadError }}</p>@endif
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button type="button" wire:click="resetFiltersToToday" icon="arrow-path">Restablecer a hoy</flux:button>
                <div class="flex gap-2">
                    <flux:button type="button" wire:click="cancelFilters">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary" icon="funnel" wire:loading.attr="disabled" wire:target="applyFilters">Aplicar filtros</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    {{-- Modal de detalle --}}
    <flux:modal wire:model.self="showDetailModal" class="w-[calc(100vw-2rem)] max-w-7xl bg-white! dark:bg-slate-900!">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-cyan-50 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-300">
                    <flux:icon.arrows-right-left class="size-6" />
                </span>
                <div>
                    <flux:heading size="lg">Transferencia {{ $selectedTransfer['nro'] ?? $selectedTransfer['numero'] ?? '#'.($selectedTransfer['id'] ?? '') }}</flux:heading>
                    <flux:text class="mt-1">Detalle de los productos y del movimiento entre stocks.</flux:text>
                </div>
            </div>
            <div class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2 lg:grid-cols-4 dark:border-slate-700 dark:bg-slate-800/50">
                <div><span class="block text-xs uppercase text-slate-500">Origen</span><strong>{{ data_get($selectedTransfer, 'stock_de.descripcion', $selectedTransfer['stock_de'] ?? '—') }}</strong></div>
                <div><span class="block text-xs uppercase text-slate-500">Destino</span><strong>{{ data_get($selectedTransfer, 'stock_para.descripcion', $selectedTransfer['stock_para'] ?? '—') }}</strong></div>
                <div><span class="block text-xs uppercase text-slate-500">Estado de envío</span><strong>{{ $selectedTransfer['estado_label'] ?? $selectedTransfer['estado'] ?? '—' }}</strong></div>
                <div><span class="block text-xs uppercase text-slate-500">Estado de recepción</span><strong>{{ $selectedTransfer['estado_recepcion_label'] ?? $selectedTransfer['estado_recepcion'] ?? '—' }}</strong></div>
            </div>
            @include('livewire.farma.partials.transfer-products', ['lines' => $detailLines, 'editable' => false])
            @include('livewire.farma.partials.transfer-totals', ['totals' => $savedTotals])
            <p class="text-sm text-slate-500">Esta transferencia fue enviada y está disponible solo para consulta.</p>
            <div class="flex justify-end gap-2">
                @if ($access['search'] && ($selectedTransfer['puede_imprimir'] ?? false) === true)
                    @include('livewire.farma.partials.transfer-print-menu', ['printId' => (int) $selectedTransfer['id']])
                @endif
                <flux:button wire:click="closeDetail">Cerrar</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal de preparación de transferencia --}}
    <flux:modal wire:model.self="showSendModal" class="w-[calc(100vw-2rem)] max-w-7xl bg-white! dark:bg-slate-900!">
        <div class="space-y-5" data-test="transfer-send-modal">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                <div>
                    <flux:heading size="lg">
                        {{ $isPrepared ? 'Preparar transferencia' : 'Nueva transferencia' }}
                    </flux:heading>
                    <flux:text class="mt-1">
                        {{ $isPrepared ? 'Selecciona lotes y cantidades. Revisa los costos, el ISV y el total antes de enviar.' : 'Selecciona el tipo y el stock de destino para preparar la transferencia.' }}
                    </flux:text>
                </div>
                @if ($isPrepared)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                        <flux:icon.clock class="size-4" /> Sin enviar
                    </span>
                @endif
            </div>

            @if (! $isPrepared)
                {{-- Paso 1: Preparar destino --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-remote-select source="transfer-types" cache="options.tipos" wire:model="tipo" label="Tipo de transferencia" />
                    <x-remote-select source="transfer-stocks" cache="options.stocks_destino" wire:model="stockPara" label="Stock de destino" :disabled="$matrixStockId !== null" />
                </div>
                @if ($matrixStockId !== null)
                    <flux:text>Destino y productos tomados de la matriz de inventario. El origen es {{ $options['stock_actual']['descripcion'] ?? 'el stock de esta sesión' }}.</flux:text>
                @endif

                <div class="flex justify-end gap-2 pt-3">
                    <flux:button type="button" wire:click="closeSend">Cancelar</flux:button>
                    <flux:button type="button" wire:click="createHeader" variant="primary" icon="check">
                        Continuar con productos
                    </flux:button>
                </div>
            @else
                {{-- Paso 2: Preparar productos y revisar importes antes del envío --}}
                <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <p><span class="text-slate-500">Tipo:</span> {{ collect($options['tipos'] ?? [])->firstWhere('id', (int) $tipo)['descripcion'] ?? '—' }}</p>
                    <p><span class="text-slate-500">Destino:</span> {{ collect($options['stocks_destino'] ?? [])->firstWhere('id', (int) $stockPara)['descripcion'] ?? '—' }}</p>
                </div>
                @if ($matrixStockId !== null)
                    @include('livewire.farma.partials.transfer-matrix-products')
                @else
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-xl bg-slate-50 p-4 dark:bg-slate-800/60">
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">Productos en la transferencia</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Selecciona un producto de la lista de lotes y define su lote y cantidad.
                        </p>
                    </div>
                    <flux:button type="button" wire:click="openProductSearch" variant="primary" icon="plus">
                        Agregar producto
                    </flux:button>
                </div>
                @endif

                @include('livewire.farma.partials.transfer-products', ['lines' => $draftLines, 'editable' => true])
                @error('details')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('stockPara')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                @include('livewire.farma.partials.transfer-totals', ['totals' => $draftTotals])
                <p class="text-sm text-slate-500">Al enviar, la transferencia quedará disponible solo para consulta.</p>
                <div class="flex justify-end gap-2 pt-2">
                    <flux:button type="button" wire:click="closeSend">Cerrar</flux:button>
                    <flux:button type="button" wire:click="finishTransfer" wire:loading.attr="disabled" variant="primary" icon="paper-airplane" data-test="send-transfer">
                        Enviar transferencia
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:modal>

    {{-- Modal de selección de lote con cantidad --}}
    <flux:modal wire:model.self="showLotSelectionModal" class="w-[calc(100vw-2rem)] max-w-4xl bg-white! dark:bg-slate-900!">
        <div class="min-w-0 space-y-4">
            <div class="border-b border-slate-200 pb-3 dark:border-slate-800">
                <flux:heading size="lg">Seleccionar Lote y Cantidad</flux:heading>
                <flux:text class="mt-1 break-words">
                    Producto: <strong class="text-slate-900 dark:text-white">{{ $pendingProduct['descripcion'] ?? '' }}</strong>
                    @if (!empty($pendingProduct['codigo']))
                        · <span class="text-xs text-slate-500">Código: {{ $pendingProduct['codigo'] }}</span>
                    @endif
                </flux:text>
            </div>

            @if ($pendingConversion !== [])
                <div class="space-y-3 rounded-xl border border-amber-300 p-4">
                    <p>Hay una conversión pendiente de confirmar. Reintenta para consultar su resultado sin duplicarla.</p>
                    <flux:button wire:click="convertProduct" wire:loading.attr="disabled" variant="primary">Confirmar conversión pendiente</flux:button>
                </div>
            @elseif ($conversionOptions !== [])
                <div class="space-y-3 rounded-xl border border-cyan-300 p-4">
                    <flux:heading>Convertir padre a hijo</flux:heading>
                    <x-remote-select source="transfer-children" cache="conversionOptions.hijos" :filters="['parent' => $conversionOptions['lote'] ?? null]" wire:model="conversionChild" label="Producto hijo" />
                    <flux:input wire:model.live="conversionQuantity" type="number" min="1" max="{{ min(999, (int) $conversionOptions['disponible']) }}" label="Presentaciones padre a abrir" />
                    <p class="text-sm">Cada padre genera {{ number_format($conversionOptions['unidades_por_padre']) }} unidades base hijo. Se generarán {{ number_format(max(0, $conversionQuantity) * $conversionOptions['unidades_por_padre']) }} unidades.</p>
                    <p class="text-sm text-amber-700 dark:text-amber-300">La conversión modifica las existencias al confirmar y se conserva aunque cierres la transferencia.</p>
                    <div class="flex justify-end gap-2">
                        <flux:button wire:click="cancelConversion">Volver a lotes</flux:button>
                        <flux:button wire:click="convertProduct" wire:loading.attr="disabled" variant="primary">Convertir y agregar hijo</flux:button>
                    </div>
                </div>
            @else
            <div class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Lotes disponibles en stock:</p>
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <span>{{ $lotSelectionTotal }} resultados · {{ $lotSelectionPage }} / {{ $lotSelectionLastPage }}</span>
                    <div class="flex gap-3"><flux:button wire:click="changeLotSelectionPage(-1)" size="sm" :disabled="$lotSelectionPage <= 1">Anterior</flux:button><flux:button wire:click="changeLotSelectionPage(1)" size="sm" :disabled="$lotSelectionPage >= $lotSelectionLastPage">Siguiente</flux:button></div>
                </div>
                <div class="max-h-96 overflow-y-auto space-y-2">
                    @foreach ($availableLotsForProduct as $availableLot)
                        @php($lotId = (int) $availableLot['id'])
                        <div wire:key="select-lot-{{ $lotId }}" class="flex min-w-0 flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/60">
                            <div class="min-w-0">
                                <div class="font-medium text-slate-900 dark:text-white">
                                    Lote: <span class="break-all font-mono text-cyan-700 dark:text-cyan-400">{{ $availableLot['lote'] }}</span>
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                    <span>Elaboración: {{ $availableLot['fecha_elaboracion'] ?? $availableLot['fecha_ela'] ?? 'Sin fecha' }}</span>
                                    <span>Vence: {{ $availableLot['fecha_expiracion'] ?? $availableLot['fecha_exp'] ?? 'Sin fecha' }}</span>
                                    <span>·</span>
                                    <span class="font-medium text-emerald-700 dark:text-emerald-400">
                                        Disponible: {{ number_format((int) $availableLot['cantidad']) }} u.
                                    </span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                                    <span class="font-semibold">Costo: {{ is_numeric($availableLot['costo'] ?? null) ? 'L '.number_format((float) $availableLot['costo'], 2) : 'Sin costo registrado' }}</span>
                                    <flux:badge :color="filter_var($availableLot['isv'] ?? false, FILTER_VALIDATE_BOOL) ? 'amber' : 'zinc'" size="sm">
                                        {{ filter_var($availableLot['isv'] ?? false, FILTER_VALIDATE_BOOL) ? 'ISV gravado' : 'Exento de ISV' }}
                                    </flux:badge>
                                </div>
                            </div>
                            <div class="flex min-w-0 flex-wrap items-center justify-end gap-2">
                                <flux:button type="button" wire:click="openConversion({{ $lotId }})" size="sm" wire:loading.attr="disabled">Convertir a hijo</flux:button>
                                <div class="w-28 shrink-0">
                                <flux:input
                                    wire:model="lotQuantities.{{ $lotId }}"
                                    type="number"
                                    min="1"
                                    max="{{ (int) $availableLot['cantidad'] }}"
                                    placeholder="Cant."
                                    aria-label="Cantidad a agregar del lote {{ $availableLot['lote'] }}"
                                />
                                </div>
                                <flux:button
                                    type="button"
                                    wire:click="selectLotFromModal({{ $lotId }})"
                                    size="sm"
                                    variant="primary"
                                    icon="plus"
                                    :disabled="! is_numeric($availableLot['costo'] ?? null)"
                                >
                                    Agregar lote
                                </flux:button>
                            </div>
                            @error('lotQuantities.'.$lotId)<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="flex justify-end pt-2 border-t border-slate-200 dark:border-slate-800">
                <flux:button type="button" wire:click="closeLotSelection">Cancelar</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal de confirmación de recepción --}}
    <flux:modal wire:model.self="showReceiveModal" class="md:w-120">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Confirmar recepción</flux:heading>
                <flux:text class="mt-2">
                    Al confirmar, los lotes de la transferencia ingresarán al stock asignado y su recepción pasará a <strong>RECIBIDA</strong>.
                </flux:text>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300">
                Verifica los productos antes de confirmar. Esta acción no se puede deshacer desde esta pantalla.
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelReceive">Cancelar</flux:button>
                <flux:button wire:click="receive" variant="primary" icon="inbox-arrow-down">Confirmar recepción</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Buscador de productos registrados en lotes --}}
    @if ($matrixStockId === null)<livewire:shared.advanced-product-search />@endif
</div>
