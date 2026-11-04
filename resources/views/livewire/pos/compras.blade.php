<div class="space-y-6" data-test="purchasespos-screen">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><flux:heading size="xl">Compras POS</flux:heading><flux:text class="mt-1">Consulta las compras enviadas desde los puntos de venta.</flux:text></div>
        <div class="flex gap-2">
            <flux:button wire:click="refresh" icon="arrow-path" wire:loading.attr="disabled">Actualizar</flux:button>
            @if ($access['filter'])
                <flux:button wire:click="openFilters" icon="funnel" data-test="filter-purchasespos">Filtros</flux:button>
                @if ($filters !== [])<flux:button wire:click="clearFilters">Limpiar filtros</flux:button>@endif
            @endif
        </div>
    </div>
    @if ($loadError)<flux:callout variant="danger" heading="No fue posible consultar las compras">{{ $loadError }}</flux:callout>@endif
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 p-4 dark:border-slate-800"><flux:text>{{ $total }} recepciones {{ $filters !== [] ? 'con los filtros aplicados' : 'registradas' }}</flux:text></div>
        <div class="overflow-x-auto p-4">
            <flux:table>
                <flux:table.columns>
                    @foreach (['Factura CAI', 'Fecha', 'Stock', 'Proveedor', 'Usuario', 'Total', 'Estado', 'Detalle'] as $label)<flux:table.column>{{ $label }}</flux:table.column>@endforeach
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($purchases as $purchase)
                        <flux:table.row :key="$purchase['id']">
                            <flux:table.cell>{{ $purchase['invoice_cai'] }}</flux:table.cell>
                            <flux:table.cell>{{ \Carbon\Carbon::parse($purchase['date'])->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $purchase['stock'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $purchase['supplier'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $purchase['operator'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">L {{ $purchase['total'] }}</flux:table.cell>
                            <flux:table.cell><flux:badge :color="$purchase['status'] === 'accepted' ? 'green' : 'red'">{{ $purchase['status_label'] }}</flux:badge></flux:table.cell>
                            <flux:table.cell>@if ($access['show'])<flux:button wire:click="openDocument('{{ $purchase['id'] }}')" icon="eye" variant="ghost" size="sm" data-test="show-purchasepos">Ver</flux:button>@endif</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            @if ($purchases === [] && ! $loadError)<div class="p-10 text-center text-slate-500">No hay compras que coincidan con la consulta.</div>@endif
        </div>
        @if ($lastPage > 1)
            <div class="flex items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800">
                <flux:text>Página {{ $page }} de {{ $lastPage }}</flux:text>
                <div class="flex gap-2"><flux:button wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Anterior</flux:button><flux:button wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Siguiente</flux:button></div>
            </div>
        @endif
    </section>
    <flux:modal wire:model.self="showFilters" class="w-full md:w-[42rem]">
        <form wire:submit="applyFilters" class="space-y-5">
            <flux:heading size="lg">Filtrar compras POS</flux:heading>
            <x-remote-select source="pos-purchase-stocks" cache="choices.stocks" wire:model="stockId" label="Stock" />
            <flux:input wire:model.live.debounce.400ms="supplierSearch" label="Buscar proveedor" maxlength="180"/>
            <x-remote-select source="pos-purchase-suppliers" cache="choices.suppliers" wire:model="supplierId" label="Proveedor" />
            <flux:input wire:model.live.debounce.400ms="productSearch" label="Buscar producto por código, código de barras o descripción" maxlength="180"/>
            <x-remote-select source="pos-purchase-products" cache="choices.products" wire:model="productId" label="Producto" />
            <div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="dateFrom" type="date" label="Desde"/><flux:input wire:model="dateTo" type="date" label="Hasta"/></div>
            <flux:select wire:model="statusFilter" label="Estado"><option value="">Todos</option><option value="accepted">Aceptada</option><option value="rejected">Rechazada</option></flux:select>
            <div class="flex justify-end gap-2"><flux:button type="button" wire:click="$set('showFilters', false)">Cancelar</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Aplicar filtros</flux:button></div>
        </form>
    </flux:modal>
    <flux:modal wire:model.self="showDocument" class="w-[min(76rem,95vw)] max-w-none">
        @if ($document)
            <div class="space-y-5" data-test="purchasepos-document">
                <flux:heading size="lg">Factura de compra POS</flux:heading>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach (['invoice_cai' => 'Factura CAI', 'supplier' => 'Proveedor', 'stock' => 'Stock', 'operator' => 'Usuario', 'equipment' => 'Equipo', 'status_label' => 'Estado'] as $field => $label)
                        <div><flux:text>{{ $label }}</flux:text><p class="font-medium break-words">{{ $document[$field] ?? '—' }}</p></div>
                    @endforeach
                    <div><flux:text>Fecha</flux:text><p>{{ \Carbon\Carbon::parse($document['date'])->format('d/m/Y') }}</p></div>
                </div>
                @if ($document['reason'])<flux:callout variant="warning" heading="Recepción rechazada">{{ $document['reason'] }}</flux:callout>@endif
                <div class="max-h-[50vh] overflow-auto rounded-lg border border-slate-200 dark:border-slate-800">
                    <table class="w-full text-sm"><thead><tr class="border-b text-left">@foreach (['Código / descripción', 'Cantidad', 'Unid. compra', 'Unidades base', 'Lote', 'Fecha elab.', 'Fecha exp.', 'Costo', 'ISV', 'Subtotal'] as $label)<th class="p-3 whitespace-nowrap">{{ $label }}</th>@endforeach</tr></thead>
                    <tbody>@foreach ($document['lines'] as $line)<tr class="border-b border-slate-100 dark:border-slate-800">
                        <td class="p-3 min-w-56"><p>{{ $line['code'] ?? '—' }}</p><p>{{ $line['description'] ?? 'Producto no disponible en el catálogo' }}</p></td>
                        @foreach (['quantity', 'purchase_factor', 'base_quantity', 'lot'] as $field)<td class="p-3">{{ $line[$field] }}</td>@endforeach
                        @foreach (['manufactured', 'expires'] as $field)<td class="p-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($line[$field])->format('d/m/Y') }}</td>@endforeach
                        <td class="p-3 whitespace-nowrap">L {{ $line['cost'] }}</td><td class="p-3 whitespace-nowrap">{{ $line['has_tax'] ? 'Sí (15 %)' : 'No' }}</td><td class="p-3 whitespace-nowrap text-right">L {{ $line['total'] }}</td>
                    </tr>@endforeach</tbody></table>
                </div>
                <dl class="ml-auto grid w-64 grid-cols-2 gap-2">
                    <dt>Subtotal</dt><dd class="text-right">L {{ $document['subtotal'] }}</dd><dt>ISV (15 %)</dt><dd class="text-right">L {{ $document['tax'] }}</dd><dt class="font-semibold">Total</dt><dd class="text-right font-semibold">L {{ $document['total'] }}</dd>
                </dl>
                <div class="flex justify-end"><flux:button wire:click="closeDocument" variant="primary">Cerrar</flux:button></div>
            </div>
        @endif
    </flux:modal>
</div>
