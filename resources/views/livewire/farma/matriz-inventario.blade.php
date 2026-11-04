<div class="farma-screen" data-test="inventory-matrix-screen">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><flux:heading size="xl">Matriz de inventario</flux:heading><flux:text>Solo productos con punto de reorden mayor que cero. Con existencia total 0 se solicita el máximo; con existencia positiva, el faltante hasta el mínimo. Datos de la última actualización de cada stock.</flux:text></div>
        <flux:button wire:click="goToPage({{ $page }})" icon="arrow-path" wire:loading.attr="disabled">Actualizar</flux:button>
    </div>
    @if ($canFilter)
        <div class="flex flex-wrap items-end gap-4">
            <x-remote-select source="inventory-stocks" cache="stocks" wire:model.live="stockId" label="Stock" />
            <flux:input wire:model.live.debounce.350ms="search" label="Producto o código" maxlength="200" />
        </div>
    @endif
    @if ($canTransfer)
        <div class="flex flex-wrap items-center gap-3">
            <flux:button wire:click="selectPage" :disabled="$stockId === '' || $records === []" wire:loading.attr="disabled">Seleccionar esta página</flux:button>
            <flux:button wire:click="prepareTransfer" variant="primary" icon="arrows-right-left" :disabled="$stockId === '' || $selectedProducts === []" wire:loading.attr="disabled" data-test="matrix-create-transfer">Crear transferencia ({{ count($selectedProducts) }})</flux:button>
            <flux:text>{{ $stockId === '' ? 'Selecciona un stock destino para elegir los productos a transferir.' : 'Marca los productos y luego selecciona sus lotes y cantidades en Transferencias.' }}</flux:text>
        </div>
        @error('selectedProducts')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @error('stockId')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    @endif
    @if ($loadError)<flux:callout variant="danger" heading="No se pudo consultar la matriz">{{ $loadError }}</flux:callout>@endif
    <section class="farma-table-panel rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div class="farma-table-scroll" tabindex="0" role="region" aria-label="Tabla desplazable">
        <flux:table>
            <flux:table.columns>@if ($canTransfer)<flux:table.column>Seleccionar</flux:table.column>@endif @foreach (['Stock', 'Código', 'Producto', 'Mínimo', 'Máximo', 'Existencia total', 'Punto de reorden', 'Última actualización de existencias'] as $label)<flux:table.column>{{ $label }}</flux:table.column>@endforeach</flux:table.columns>
            <flux:table.rows>
                @foreach ($records as $row)
                    <flux:table.row :key="$row['id']">
                        @if ($canTransfer)
                            <flux:table.cell><input type="checkbox" wire:click="toggleProduct({{ $row['id'] }})" @checked(isset($selectedProducts[(int) ($row['producto_id'] ?? 0)])) @disabled($stockId === '') wire:loading.attr="disabled" aria-label="Seleccionar {{ $row['descripcion'] }}" class="size-4 cursor-pointer rounded accent-cyan-600 disabled:cursor-default" /></flux:table.cell>
                        @endif
                        <flux:table.cell>{{ $row['stock_nombre'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['codigo'] ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $row['descripcion'] }}</flux:table.cell>
                        @foreach (['minimo', 'maximo', 'existencia_total', 'punto_reorden'] as $field)<flux:table.cell>{{ $row[$field] ?? '—' }}</flux:table.cell>@endforeach
                        <flux:table.cell>{{ ! empty($row['existencias_actualizadas_at']) ? \Carbon\Carbon::parse($row['existencias_actualizadas_at'], config('app.timezone'))->timezone('America/Tegucigalpa')->format('d/m/Y h:i A') : 'Pendiente' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        </div>
        @if ($records === [] && ! $loadError)<flux:text class="p-8 text-center">No hay productos con reorden pendiente que coincidan con la consulta.</flux:text>@endif
        <div class="mt-4 flex items-center justify-between gap-4">
            <flux:text>{{ $total }} productos · Página {{ $page }} de {{ $lastPage }}</flux:text>
            <div class="flex gap-2"><flux:button wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1" wire:loading.attr="disabled">Anterior</flux:button><flux:button wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage" wire:loading.attr="disabled">Siguiente</flux:button></div>
        </div>
    </section>
</div>
