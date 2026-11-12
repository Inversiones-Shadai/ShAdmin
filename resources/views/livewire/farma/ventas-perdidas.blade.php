<div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">Ventas perdidas</flux:heading>
        <flux:button wire:click="openFilters" icon="adjustments-horizontal" wire:loading.attr="disabled">Filtros</flux:button>
    </header>

    <flux:modal wire:model.self="showFiltersModal" class="w-[calc(100vw-2rem)] min-w-0 max-w-3xl">
        <form wire:submit="applyFilters" class="space-y-5">
            <flux:heading size="lg">Filtros de ventas perdidas</flux:heading>
            <div class="grid items-start gap-4 sm:grid-cols-2">
                <x-remote-select source="lost-sale-stocks" cache="choices.stocks" wire:model="stockId" label="Sucursal" placeholder="Todas las sucursales" />
                <x-remote-select source="lost-sale-users" cache="choices.users" wire:model="userId" label="Usuario POS" placeholder="Todos los usuarios" />
                <x-remote-select source="lost-sale-products" cache="choices.products" wire:model="productId" label="Producto" placeholder="Todos los productos" class="sm:col-span-2" />
                <flux:input wire:model="dateFrom" type="date" label="Desde" />
                <flux:input wire:model="dateTo" type="date" label="Hasta (inclusive)" />
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button type="button" wire:click="clearFilters" wire:loading.attr="disabled">Limpiar filtros</flux:button>
                <div class="flex flex-wrap gap-2">
                    <flux:button type="button" wire:click="$set('showFiltersModal', false)">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary" icon="funnel" wire:loading.attr="disabled" wire:target="applyFilters">Aplicar filtros</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    @error('report')
        <p role="alert" class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @else
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="ps-4!">Código</flux:table.column>
                    <flux:table.column>Producto</flux:table.column>
                    <flux:table.column>Sucursal</flux:table.column>
                    <flux:table.column>Usuario POS</flux:table.column>
                    <flux:table.column align="end">Registros</flux:table.column>
                    <flux:table.column>Último registro</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($rows as $row)
                        <flux:table.row wire:key="lost-sale-{{ $row['producto_id'] }}-{{ $row['stock_id'] ?? 0 }}-{{ $row['usuario_pos_id'] ?? 0 }}">
                            <flux:table.cell class="ps-4!">{{ $row['codigo'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['producto'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['stock'] ?? (isset($row['stock_id']) ? 'Sucursal #'.$row['stock_id'] : '—') }}</flux:table.cell>
                            <flux:table.cell>{{ $row['usuario'] ?? (isset($row['usuario_pos_id']) ? 'Usuario #'.$row['usuario_pos_id'] : '—') }}</flux:table.cell>
                            <flux:table.cell align="end" class="font-semibold tabular-nums">{{ number_format($row['registros']) }}</flux:table.cell>
                            <flux:table.cell>{{ \Illuminate\Support\Carbon::parse($row['ultimo_registro'], 'UTC')->timezone('America/Tegucigalpa')->format('d/m/Y H:i') }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="6" class="ps-4!">No hay ventas perdidas para estos filtros.</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
        @if ($lastPage > 1)
            <nav aria-label="Páginas del reporte" class="flex flex-wrap items-center justify-between gap-3">
                <flux:text>Página {{ $page }} de {{ $lastPage }}</flux:text>
                <div class="flex gap-2">
                    <flux:button size="sm" wire:click="loadPage({{ $page - 1 }})" :disabled="$page <= 1" wire:loading.attr="disabled">Anterior</flux:button>
                    <flux:button size="sm" wire:click="loadPage({{ $page + 1 }})" :disabled="$page >= $lastPage" wire:loading.attr="disabled">Siguiente</flux:button>
                </div>
            </nav>
        @endif
    @enderror
</div>
