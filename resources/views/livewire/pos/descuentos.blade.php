<div class="space-y-6" data-test="discountspos-screen">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div><flux:heading size="xl">Reglas de descuentos POS</flux:heading><flux:text class="mt-1">Define reglas por categoría y excepciones por producto para todos los stocks.</flux:text></div>
        @if ($access['create'])<flux:button wire:click="openCreate" variant="primary" icon="plus" :disabled="$pending !== null">Nueva regla</flux:button>@endif
    </div>
    <flux:callout>Una sola regla cubre todos los descuentos: General, 3ra edad y 4ta edad. Producto tiene prioridad sobre Categoría. Permitir descuentos conserva los porcentajes habituales del lote, incluso en una categoría excluida.</flux:callout>
    @if ($pending !== null)
        <flux:callout variant="warning"><flux:callout.heading>Solicitud pendiente de confirmar</flux:callout.heading><flux:callout.text>Se conservaron los datos originales. {{ $pending['payload']['alcance'] ?? ($pending['operation'] === 'delete' ? 'eliminación' : 'desactivación') }} {{ $pending['payload']['referencia_id'] ?? $pending['id'] }}.</flux:callout.text><flux:button wire:click="retry" wire:loading.attr="disabled">Reintentar solicitud original</flux:button></flux:callout>
    @endif
    @error('form')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
    @if ($loadError)<flux:callout variant="danger">{{ $loadError }}</flux:callout><flux:button wire:click="goToPage({{ $page }})">Reintentar consulta</flux:button>@endif
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto p-4"><flux:table><flux:table.columns>
            <flux:table.column>Aplica a</flux:table.column><flux:table.column>Categoría / Producto</flux:table.column>
            <flux:table.column>Todos los descuentos</flux:table.column><flux:table.column>Estado</flux:table.column><flux:table.column>Acciones</flux:table.column>
        </flux:table.columns><flux:table.rows>
            @foreach ($records as $record)<flux:table.row :key="$record['id']">
                <flux:table.cell>{{ $record['datos']['alcance'] === 'PRODUCTO' ? 'Producto' : 'Categoría' }}</flux:table.cell><flux:table.cell>{{ $record['datos']['descripcion'] }}</flux:table.cell>
                @php($generalMode = \App\Livewire\Pos\Descuentos::generalMode($record['datos']))
                <flux:table.cell>{{ ['HEREDAR'=>'Heredar','LOTE'=>'Permitir descuentos','EXCLUIR'=>'Excluir todos los descuentos'][$generalMode] ?? 'Regla anterior por tipo' }}</flux:table.cell>
                <flux:table.cell><flux:badge :color="$record['activo'] ? 'green' : 'zinc'">{{ $record['activo'] ? 'Activa' : 'Inactiva' }}</flux:badge></flux:table.cell>
                <flux:table.cell><div class="flex gap-1">
                    @if ($access['show'])<flux:button wire:click="openRecord({{ $record['id'] }})" size="sm" :disabled="$pending !== null">Ver</flux:button>@endif
                    @if ($access['edit'])<flux:button wire:click="openRecord({{ $record['id'] }}, true)" size="sm" :disabled="$pending !== null">Modificar</flux:button>@endif
                    @if ($access['deactivate'] && $record['activo'])<flux:button wire:click="deactivate({{ $record['id'] }})" wire:confirm="¿Desactivar esta regla? Se utilizará la siguiente regla disponible." size="sm" variant="danger" :disabled="$pending !== null">Desactivar</flux:button>@endif
                    @if ($access['delete'])
                        <flux:button
                            x-data="{ confirming: false }"
                            x-bind:disabled="confirming || $wire.pending !== null"
                            x-on:click="
                                if (confirming) return;
                                confirming = true;
                                try {
                                    const result = await window.ShAdminAlerts.alert({
                                        icon: 'warning',
                                        title: '¿Eliminar esta regla?',
                                        text: 'Se eliminará de forma permanente y se utilizará la siguiente regla disponible. Esta acción no se puede deshacer.',
                                        showCancelButton: true,
                                        confirmButtonText: 'Sí, eliminar',
                                        cancelButtonText: 'Cancelar',
                                        confirmButtonColor: '#dc2626',
                                        focusCancel: true,
                                    });
                                    if (result.isConfirmed) await $wire.delete({{ $record['id'] }});
                                } finally {
                                    confirming = false;
                                }
                            "
                            size="sm" variant="danger" :disabled="$pending !== null"
                            data-test="delete-discountspos"
                        >Eliminar</flux:button>
                    @endif
                </div></flux:table.cell>
            </flux:table.row>@endforeach
        </flux:table.rows></flux:table>@if ($records === [])<flux:text class="p-6">No hay reglas para mostrar.</flux:text>@endif</div>
        <div class="flex items-center justify-between border-t p-4"><flux:text>Página {{ $page }} de {{ $lastPage }}</flux:text><div class="flex gap-2"><flux:button wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Anterior</flux:button><flux:button wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Siguiente</flux:button></div></div>
    </section>
    <flux:modal wire:model.self="showFormModal" class="md:w-[42rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $readOnly ? 'Ver regla' : ($editingId ? 'Modificar regla' : 'Nueva regla') }}</flux:heading>
            @error('form')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
            <flux:text>Aplicación: todos los stocks.</flux:text>
            @if ($editingId)
                <flux:input :value="$original['datos']['descripcion']" :label="$alcance === 'PRODUCTO' ? 'Producto' : 'Categoría'" readonly />
            @else
                <flux:select wire:model.live="alcance" label="Aplica a" :disabled="$pending !== null"><flux:select.option value="CATEGORIA">Categoría</flux:select.option><flux:select.option value="PRODUCTO">Producto</flux:select.option></flux:select>
                @if ($alcance === 'PRODUCTO')
                    <div class="flex items-end gap-2">
                        <flux:input :value="$selectedProduct['nombre'] ?? ''" label="Producto" placeholder="Selecciona un producto" readonly />
                        <flux:button type="button" wire:click="openProductPicker" icon="magnifying-glass" :disabled="$pending !== null" wire:loading.attr="disabled">Buscar producto</flux:button>
                    </div>
                    @error('referenceId')<flux:text class="text-red-600">{{ $message }}</flux:text>@enderror
                    @if ($showProductPicker)
                        <section class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" aria-label="Lista de productos" data-test="discount-product-picker">
                            <div class="flex items-end gap-2">
                                <flux:input wire:model.live.debounce.350ms="search" wire:keydown.enter.prevent="searchReferences" label="Buscar por nombre" placeholder="Nombre del producto" maxlength="200" />
                                <flux:button type="button" wire:click="searchReferences" wire:loading.attr="disabled">Buscar</flux:button>
                            </div>
                            <div wire:loading wire:target="searchReferences,openProductPicker"><flux:text>Buscando productos…</flux:text></div>
                            <div class="max-h-96 space-y-2 overflow-y-auto" wire:loading.class="opacity-50" wire:target="searchReferences,openProductPicker">
                                @forelse ($references as $product)
                                    <article wire:key="discount-product-{{ $product['id'] }}" class="flex items-center gap-3 rounded-lg border border-slate-200 p-2 dark:border-slate-700" data-test="discount-product-card">
                                        <div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded bg-slate-50 dark:bg-slate-800">
                                            @if ($product['imagen'] ?? null)
                                                <img src="{{ $product['imagen'] }}" alt="{{ $product['nombre'] }}" class="size-16 object-contain" loading="lazy" />
                                            @else
                                                <flux:icon.photo class="size-7 text-slate-400" aria-label="Sin imagen" />
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1"><p class="break-words text-sm font-semibold">{{ $product['nombre'] }}</p><flux:text size="sm">{{ $product['codigo'] ?? '—' }}</flux:text></div>
                                        <flux:button type="button" size="sm" wire:click="selectProduct({{ $product['id'] }})" wire:loading.attr="disabled">Seleccionar</flux:button>
                                    </article>
                                @empty
                                    <flux:text class="py-5 text-center">No se encontraron productos.</flux:text>
                                @endforelse
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <flux:text size="sm">Página {{ $referencePage }} de {{ $referenceLastPage }} · 10 productos por página</flux:text>
                                <div class="flex gap-2">
                                    <flux:button type="button" size="sm" wire:click="searchReferences({{ $referencePage - 1 }})" :disabled="$referencePage <= 1" wire:loading.attr="disabled">Anterior</flux:button>
                                    <flux:button type="button" size="sm" wire:click="searchReferences({{ $referencePage + 1 }})" :disabled="$referencePage >= $referenceLastPage" wire:loading.attr="disabled">Siguiente</flux:button>
                                    <flux:button type="button" size="sm" wire:click="closeProductPicker">Cerrar lista</flux:button>
                                </div>
                            </div>
                        </section>
                    @endif
                @else
                <div class="flex gap-2"><flux:input wire:model.live.debounce.350ms="search" label="Buscar por nombre o código" maxlength="200" :disabled="$pending !== null" /><flux:button type="button" wire:click="searchReferences" :disabled="$pending !== null">Buscar</flux:button></div>
                <x-remote-select source="discount-categories" cache="references" wire:model="referenceId" :label="$alcance === 'PRODUCTO' ? 'Producto' : 'Categoría'" :disabled="$pending !== null" placeholder="Selecciona una referencia" />
                <div class="flex items-center gap-2"><flux:button type="button" wire:click="searchReferences({{ $referencePage - 1 }})" :disabled="$referencePage <= 1 || $pending !== null">Anterior</flux:button><flux:text>{{ $referencePage }} / {{ $referenceLastPage }}</flux:text><flux:button type="button" wire:click="searchReferences({{ $referencePage + 1 }})" :disabled="$referencePage >= $referenceLastPage || $pending !== null">Siguiente</flux:button></div>
                @endif
            @endif
            <flux:text>Esta opción se aplica a General, 3ra edad y 4ta edad.</flux:text>
            @if ($mode === '')<flux:callout>La regla anterior tenía opciones por tipo. Selecciona una opción general para modificarla.</flux:callout>@endif
            <flux:select wire:model="mode" label="Todos los descuentos" :disabled="$readOnly || $pending !== null" placeholder="Selecciona una opción general">
                <flux:select.option value="HEREDAR">Heredar</flux:select.option>
                <flux:select.option value="LOTE">Permitir descuentos</flux:select.option>
                <flux:select.option value="EXCLUIR">Excluir todos los descuentos</flux:select.option>
            </flux:select>
            <flux:checkbox wire:model="activo" label="Activa" :disabled="$readOnly || $pending !== null" />
            <div class="flex justify-end gap-2"><flux:button type="button" wire:click="closeForm" :disabled="$pending !== null">{{ $readOnly ? 'Cerrar' : 'Cancelar' }}</flux:button>@if (!$readOnly)<flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ $pending !== null ? 'Reintentar solicitud original' : 'Guardar' }}</flux:button>@endif</div>
        </form>
    </flux:modal>
</div>
