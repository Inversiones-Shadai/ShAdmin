<div class="space-y-6" data-test="catalogospos-screen">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div><flux:heading size="xl">Catálogos de operación POS</flux:heading><flux:text class="mt-1">Configura los medios de pago para cada stock.</flux:text></div>
        @if ($access['create'])<flux:button wire:click="openCreate" variant="primary" icon="plus" data-test="create-catalogospos">Nuevo medio de pago</flux:button>@endif
    </div>
    @if (!$showFormModal && !$showDeactivateModal)
        @error('form')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
    @endif
    @if ($loadError)<flux:callout variant="danger">{{ $loadError }}</flux:callout><flux:button wire:click="goToPage({{ $page }})">Reintentar</flux:button>@endif
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 p-4 dark:border-slate-800"><flux:text>{{ $total }} registros</flux:text></div>
        @if ($records === [])<div class="p-10 text-center text-slate-500">No hay catálogos para mostrar.</div>@else
            <div class="overflow-x-auto p-4"><flux:table>
                <flux:table.columns>
                    <flux:table.column>Stock</flux:table.column><flux:table.column>Tipo</flux:table.column><flux:table.column>Código</flux:table.column><flux:table.column>Descripción</flux:table.column><flux:table.column>Efectivo</flux:table.column><flux:table.column>Referencia</flux:table.column><flux:table.column>Estado</flux:table.column><flux:table.column align="end">Acciones</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>@foreach ($records as $record)
                    <flux:table.row :key="$record['id']">
                        <flux:table.cell>{{ $record['stock_id'] }}</flux:table.cell>
                        <flux:table.cell>{{ ['MEDIO_PAGO' => 'Medio de pago', 'IMPUESTO' => 'Impuesto', 'UNIDAD' => 'Unidad', 'POLITICA' => 'Política', 'PRECIO' => 'Precio'][$record['tipo']] ?? $record['tipo'] }}</flux:table.cell>
                        <flux:table.cell>{{ $record['codigo'] }}</flux:table.cell><flux:table.cell>{{ $record['datos']['descripcion'] ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $record['tipo'] === 'MEDIO_PAGO' ? ($record['datos']['es_efectivo'] ? 'Sí' : 'No') : '—' }}</flux:table.cell><flux:table.cell>{{ $record['tipo'] === 'MEDIO_PAGO' ? ($record['datos']['requiere_referencia'] ? 'Sí' : 'No') : '—' }}</flux:table.cell>
                        <flux:table.cell><flux:badge :color="$record['activo'] ? 'green' : 'zinc'">{{ $record['activo'] ? 'Activo' : 'Inactivo' }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end"><div class="flex justify-end gap-1">
                            @if ($record['tipo'] === 'MEDIO_PAGO' && $access['edit'])<flux:button wire:click="openEdit({{ $record['id'] }})" size="sm" icon="pencil-square" variant="ghost" tooltip="Editar medio de pago" data-test="edit-catalogospos" />@endif
                            @if ($record['tipo'] === 'MEDIO_PAGO' && $record['activo'] && $access['deactivate'])<flux:button wire:click="confirmDeactivate({{ $record['id'] }})" size="sm" icon="no-symbol" variant="ghost" tooltip="Desactivar medio de pago" data-test="deactivate-catalogospos" />@endif
                        </div></flux:table.cell>
                    </flux:table.row>
                @endforeach</flux:table.rows>
            </flux:table></div>
        @endif
        <div class="flex items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800"><flux:text>Página {{ $page }} de {{ $lastPage }}</flux:text><div class="flex gap-2"><flux:button wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Anterior</flux:button><flux:button wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Siguiente</flux:button></div></div>
    </section>
    <flux:modal wire:model.self="showFormModal" class="md:w-120">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Editar medio de pago' : 'Nuevo medio de pago' }}</flux:heading>
            @error('form')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
            @if ($editingId)
                <flux:input :value="$originalIdentity['stock_id']" label="Stock" readonly />
                <flux:input :value="$originalIdentity['payment_code']" label="Código" readonly />
            @else
                <x-remote-select source="pos-stocks" cache="stocks" wire:model="stockId" label="Stock" placeholder="Selecciona el stock" />
                <flux:input wire:model="codigo" label="Código" maxlength="30" />
            @endif
            <flux:input wire:model="descripcion" label="Descripción" maxlength="100" />
            <flux:checkbox wire:model="esEfectivo" label="Es efectivo" />
            <flux:checkbox wire:model="requiereReferencia" label="Requiere referencia" />
            <flux:checkbox wire:model="activo" label="Activo" />
            <div class="flex justify-end gap-2"><flux:button type="button" wire:click="closeForm" :disabled="$pendingId !== null">Cancelar</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ $pendingId ? 'Reintentar' : 'Guardar' }}</flux:button></div>
        </form>
    </flux:modal>
    <flux:modal wire:model.self="showDeactivateModal" class="md:w-120"><div class="space-y-5">
        <flux:heading size="lg">Desactivar medio de pago</flux:heading><flux:text>{{ $deactivatingName }}</flux:text>
        @error('form')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
        <div class="flex justify-end gap-2"><flux:button wire:click="cancelDeactivate" :disabled="$pendingId !== null">Cancelar</flux:button><flux:button wire:click="deactivate" variant="danger" wire:loading.attr="disabled">{{ $pendingId ? 'Reintentar' : 'Desactivar' }}</flux:button></div>
    </div></flux:modal>
</div>
