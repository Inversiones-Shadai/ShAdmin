<div class="space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Reportes</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">Reporte de sorteos</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                Consulta los tickets emitidos por sorteo, cliente, usuario, stock o número de ticket.
            </p>
        </div>

        @if ($filterActive)
            <div class="flex flex-wrap items-center gap-2">
                <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300">
                    {{ number_format($total) }} {{ $total === 1 ? 'resultado' : 'resultados' }}
                </div>
                @if ($access['export'] && $total > 0)
                    <flux:button
                        wire:click="openExport"
                        variant="primary"
                        icon="arrow-down-tray"
                        data-test="export-report-button"
                    >
                        Exportar
                    </flux:button>
                @endif
            </div>
        @endif
    </section>

    @if ($loadError)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="No fue posible generar el reporte">
            {{ $loadError }}
        </flux:callout>
    @endif

    <form wire:submit="applyFilters" class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-slate-950 dark:text-white">Criterios del reporte</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Combina uno o varios filtros para reducir los resultados.
                </p>
            </div>
            <flux:input
                wire:model="ticketNumber"
                label="Número de ticket"
                icon="ticket"
                placeholder="Ej. 1024"
                class="w-full sm:max-w-xs"
            />
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @if ($access['drawCatalog'])
                <section class="space-y-3 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Sorteo</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Elige un sorteo de la lista.</p>
                    </div>
                    <div class="flex gap-2">
                        <flux:button type="button" wire:click="openCatalog('draws')" icon-trailing="magnifying-glass" class="min-w-0 flex-1 justify-between" data-test="open-draw-catalog">
                            {{ $selectedDraw !== '' ? ($selectedLabels['draws'] ?? "Sorteo #{$selectedDraw}") : 'Todos los sorteos' }}
                        </flux:button>
                        @if ($selectedDraw !== '')
                            <flux:button type="button" wire:click="clearCatalogSelection('draws')" icon="x-mark" variant="ghost" square tooltip="Quitar sorteo" />
                        @endif
                    </div>
                </section>
            @endif

            @if ($access['clientCatalog'])
                <section class="space-y-3 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Cliente</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Elige un cliente de la lista.</p>
                    </div>
                    <div class="flex gap-2">
                        <flux:button type="button" wire:click="openCatalog('clients')" icon-trailing="magnifying-glass" class="min-w-0 flex-1 justify-between" data-test="open-client-catalog">
                            {{ $selectedClient !== '' ? ($selectedLabels['clients'] ?? "Cliente #{$selectedClient}") : 'Todos los clientes' }}
                        </flux:button>
                        @if ($selectedClient !== '')
                            <flux:button type="button" wire:click="clearCatalogSelection('clients')" icon="x-mark" variant="ghost" square tooltip="Quitar cliente" />
                        @endif
                    </div>
                </section>
            @endif

            @if ($access['userCatalog'])
                <section class="space-y-3 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Usuario</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Elige quien registró el ticket.</p>
                    </div>
                    <div class="flex gap-2">
                        <flux:button type="button" wire:click="openCatalog('users')" icon-trailing="magnifying-glass" class="min-w-0 flex-1 justify-between" data-test="open-user-catalog">
                            {{ $selectedUser !== '' ? ($selectedLabels['users'] ?? "Usuario #{$selectedUser}") : 'Todos los usuarios' }}
                        </flux:button>
                        @if ($selectedUser !== '')
                            <flux:button type="button" wire:click="clearCatalogSelection('users')" icon="x-mark" variant="ghost" square tooltip="Quitar usuario" />
                        @endif
                    </div>
                </section>
            @endif

            @if ($access['stockCatalog'])
                <section class="space-y-3 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Stock</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Elige un punto de emisión.</p>
                    </div>
                    <div class="flex gap-2">
                        <flux:button type="button" wire:click="openCatalog('stocks')" icon-trailing="magnifying-glass" class="min-w-0 flex-1 justify-between" data-test="open-stock-catalog">
                            {{ $selectedStock !== '' ? ($selectedLabels['stocks'] ?? "Stock #{$selectedStock}") : 'Todos los stocks' }}
                        </flux:button>
                        @if ($selectedStock !== '')
                            <flux:button type="button" wire:click="clearCatalogSelection('stocks')" icon="x-mark" variant="ghost" square tooltip="Quitar stock" />
                        @endif
                    </div>
                </section>
            @endif
        </div>

        @error('filters')
            <flux:callout variant="warning" icon="exclamation-triangle">
                {{ $message }}
            </flux:callout>
        @enderror

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end dark:border-slate-800">
            @if ($filterActive)
                <flux:button type="button" wire:click="clearFilters">
                    Limpiar reporte
                </flux:button>
            @endif
            <flux:button type="submit" variant="primary" icon="funnel" data-test="filter-report-button">
                Generar reporte
            </flux:button>
        </div>
    </form>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <h2 class="font-semibold text-slate-950 dark:text-white">Tickets encontrados</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                @if ($filterActive)
                    Mostrando {{ count($tickets) }} de {{ number_format($total) }} resultados.
                @else
                    Define al menos un criterio para consultar el reporte.
                @endif
            </p>
        </div>

        @if (! $filterActive)
            <div class="px-6 py-14 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-300">
                    <flux:icon.document-chart-bar class="size-6" />
                </div>
                <h3 class="mt-4 font-medium text-slate-950 dark:text-white">El reporte está listo para consultar</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Usa los filtros superiores y presiona “Generar reporte”.
                </p>
            </div>
        @elseif ($tickets === [])
            <div class="px-6 py-14 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    <flux:icon.magnifying-glass class="size-6" />
                </div>
                <h3 class="mt-4 font-medium text-slate-950 dark:text-white">No se encontraron tickets</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Ajusta los criterios e intenta nuevamente.
                </p>
            </div>
        @else
            <div class="overflow-x-auto px-4 pb-4 pt-2 sm:px-6" data-test="report-results-table">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="py-3.5">Ticket</flux:table.column>
                        <flux:table.column class="py-3.5">Sorteo</flux:table.column>
                        <flux:table.column class="py-3.5">Cliente</flux:table.column>
                        <flux:table.column class="py-3.5">Usuario</flux:table.column>
                        <flux:table.column class="py-3.5">Stock</flux:table.column>
                        <flux:table.column class="py-3.5">Fecha</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($tickets as $ticket)
                            <flux:table.row :key="$ticket['id'] ?: $ticket['number']">
                                <flux:table.cell class="py-4 font-mono font-semibold text-slate-950 dark:text-white">
                                    #{{ $ticket['number'] }}
                                </flux:table.cell>
                                <flux:table.cell class="min-w-48 py-4">{{ $ticket['promotion'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="min-w-56 py-4">
                                    <div class="font-medium text-slate-950 dark:text-white">{{ $ticket['client'] ?: '—' }}</div>
                                    @if ($ticket['document'])
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $ticket['document'] }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="min-w-40 py-4">{{ $ticket['user'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="min-w-40 py-4">{{ $ticket['stock'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap py-4">{{ $ticket['created_at'] ?: '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif

        @if ($filterActive && $lastPage > 1)
            <nav class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-700" aria-label="Paginación del reporte">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Página {{ $page }} de {{ $lastPage }}
                </p>

                <div class="flex items-center gap-2">
                    <flux:button wire:click="previousPage" icon="chevron-left" size="sm" :disabled="$page <= 1">
                        Anterior
                    </flux:button>
                    <flux:button wire:click="nextPage" icon-trailing="chevron-right" size="sm" :disabled="$page >= $lastPage">
                        Siguiente
                    </flux:button>
                </div>
            </nav>
        @endif
    </section>

    <flux:modal wire:model.self="showCatalogModal" class="w-full md:w-[48rem]">
        <div class="space-y-5" data-test="report-catalog-modal">
            <div>
                <flux:heading size="lg">Seleccionar {{ strtolower($catalogTitle) }}</flux:heading>
                <flux:text class="mt-1">Elige un registro de la tabla para aplicarlo al reporte.</flux:text>
            </div>

            @if ($catalogCanSearch)
                <form wire:submit="searchCatalog" class="flex gap-2">
                    <flux:input wire:model.live.debounce.350ms="catalogSearch" placeholder="Filtrar {{ strtolower($catalogTitle) }}..." class="min-w-0 flex-1" data-test="report-catalog-search" />
                    <flux:button type="submit" icon="magnifying-glass" tooltip="Filtrar" />
                    @if ($catalogSearch !== '')
                        <flux:button type="button" wire:click="resetCatalogSearch" icon="x-mark" variant="ghost" tooltip="Limpiar filtro" />
                    @endif
                </form>
                @error('catalogSearch')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            @endif

            @if ($catalogError)
                <flux:callout variant="danger" icon="exclamation-triangle" heading="No fue posible cargar la lista">
                    {{ $catalogError }}
                </flux:callout>
            @endif

            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                <div class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    {{ number_format($catalogTotal) }} {{ $catalogTotal === 1 ? 'registro' : 'registros' }}
                </div>
                @if ($catalogRows === [])
                    <p class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        @if ($catalogCanSearch && $catalogSearch === '' && $catalogTotal === 0)
                            No hay registros en esta página. Puedes usar el filtro para buscar.
                        @else
                            No se encontraron registros.
                        @endif
                    </p>
                @else
                    <div class="max-h-96 overflow-auto">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>ID</flux:table.column>
                                <flux:table.column>Nombre o descripción</flux:table.column>
                                <flux:table.column align="end">Acción</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($catalogRows as $item)
                                    <flux:table.row :key="$activeCatalog.'-'.$item['id']">
                                        <flux:table.cell class="font-mono text-xs">{{ $item['id'] }}</flux:table.cell>
                                        <flux:table.cell variant="strong">{{ $item['label'] ?: '—' }}</flux:table.cell>
                                        <flux:table.cell align="end">
                                            <flux:button wire:click="selectCatalogItem({{ $item['id'] }})" size="sm" variant="primary" data-test="select-report-catalog-item">
                                                Seleccionar
                                            </flux:button>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif
            </div>

            @if ($catalogLastPage > 1)
                <nav class="flex items-center justify-between gap-3" aria-label="Paginación del selector">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Página {{ $catalogPage }} de {{ $catalogLastPage }}</p>
                    <div class="flex gap-2">
                        <flux:button wire:click="goToCatalogPage({{ $catalogPage - 1 }})" icon="chevron-left" size="sm" :disabled="$catalogPage <= 1">Anterior</flux:button>
                        <flux:button wire:click="goToCatalogPage({{ $catalogPage + 1 }})" icon-trailing="chevron-right" size="sm" :disabled="$catalogPage >= $catalogLastPage">Siguiente</flux:button>
                    </div>
                </nav>
            @endif

            <div class="flex justify-end">
                <flux:button type="button" wire:click="closeCatalog">Cerrar</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showExportModal" class="w-full md:w-[32rem]">
        <form wire:submit="exportReport" class="space-y-6">
            <div>
                <flux:heading size="lg">Exportar reporte</flux:heading>
                <flux:text class="mt-2">
                    Se exportarán los {{ number_format($total) }} registros que coinciden con los filtros activos.
                </flux:text>
            </div>

            <flux:radio.group wire:model="exportFormat" label="Selecciona el formato" variant="segmented">
                <flux:radio value="pdf" icon="document-text">PDF</flux:radio>
                <flux:radio value="xlsx" icon="table-cells">Excel</flux:radio>
            </flux:radio.group>
            @error('exportFormat')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-950/50 dark:text-slate-300">
                <p class="font-medium text-slate-900 dark:text-white">Contenido incluido</p>
                <p class="mt-1">Ticket, sorteo, cliente, identidad, usuario, stock y fecha de emisión.</p>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeExport">
                    Cancelar
                </flux:button>
                <flux:button
                    type="submit"
                    variant="primary"
                    icon="arrow-down-tray"
                    wire:loading.attr="disabled"
                    wire:target="exportReport"
                >
                    <span wire:loading.remove wire:target="exportReport">Descargar</span>
                    <span wire:loading wire:target="exportReport">Generando...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
