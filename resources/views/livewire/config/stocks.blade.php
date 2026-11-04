<div class="space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">Stocks</h1>
        </div>

        @if ($access['createStocks'])
            <flux:button wire:click="openCreate" variant="primary" icon="plus" data-test="create-stock-button">
                Nuevo stock
            </flux:button>
        @endif
    </section>

    @if ($loadError)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="No fue posible completar la solicitud">
            {{ $loadError }}
        </flux:callout>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div>
                <h2 class="font-semibold text-slate-950 dark:text-white">Listado de stocks</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ count($visibleStocks) }} en esta página · {{ $total }} en total
                </p>
            </div>

            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Buscar stock..."
                class="w-full sm:max-w-xs"
            />
        </div>

        @if ($visibleStocks === [])
            <div class="px-6 py-14 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    <flux:icon.stock class="size-6" />
                </div>
                <h3 class="mt-4 font-medium text-slate-950 dark:text-white">No hay stocks para mostrar</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $search !== '' ? 'Prueba con otro término de búsqueda.' : 'Crea el primer stock para comenzar.' }}
                </p>
            </div>
        @else
            <div data-test="stocks-table-container" class="overflow-x-auto px-4 pb-4 pt-2 sm:px-6">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="py-3.5">ID</flux:table.column>
                        <flux:table.column class="py-3.5">Descripción</flux:table.column>
                        <flux:table.column class="py-3.5">Teléfono</flux:table.column>
                        <flux:table.column class="py-3.5">Ubicación</flux:table.column>
                        <flux:table.column class="py-3.5">Estado</flux:table.column>
                        <flux:table.column class="py-3.5" align="end">Acciones</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($visibleStocks as $stock)
                            <flux:table.row :key="$stock['id']">
                                <flux:table.cell class="py-4 font-mono text-xs">{{ $stock['id'] }}</flux:table.cell>
                                <flux:table.cell class="min-w-48 py-4" variant="strong">
                                    {{ $stock['descripcion'] ?: '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="min-w-36 py-4">
                                    {{ $stock['telefono'] ?: '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="min-w-52 py-4">
                                    {{ $stock['ubicacion'] ?: '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => strtoupper(trim($stock['state_label'])) === 'ACTIVO',
                                        'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300' => strtoupper(trim($stock['state_label'])) === 'INACTIVO',
                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! in_array(strtoupper(trim($stock['state_label'])), ['ACTIVO', 'INACTIVO'], true),
                                    ])>
                                        {{ $stock['state_label'] ?: 'Sin estado' }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="py-4" align="end">
                                    <div class="flex justify-end gap-1">
                                        @if ($access['editStocks'])
                                            <flux:button
                                                wire:click="openEdit({{ $stock['id'] }})"
                                                icon="pencil-square"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Editar stock"
                                                data-test="edit-stock-button"
                                            />
                                        @endif
                                        @if ($access['deleteStocks'])
                                            <flux:button
                                                wire:click="confirmDelete({{ $stock['id'] }})"
                                                icon="trash"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Eliminar stock"
                                                data-test="delete-stock-button"
                                            />
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif

        @if ($lastPage > 1)
            <nav class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-700" aria-label="Paginación de stocks">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Página {{ $page }} de {{ $lastPage }}
                </p>

                <div class="flex items-center gap-2">
                    <flux:button
                        wire:click="previousPage"
                        wire:loading.attr="disabled"
                        icon="chevron-left"
                        size="sm"
                        :disabled="$page <= 1"
                    >
                        Anterior
                    </flux:button>
                    <flux:button
                        wire:click="nextPage"
                        wire:loading.attr="disabled"
                        icon-trailing="chevron-right"
                        size="sm"
                        :disabled="$page >= $lastPage"
                    >
                        Siguiente
                    </flux:button>
                </div>
            </nav>
        @endif
    </section>

    <flux:modal wire:model.self="showFormModal" class="w-full md:w-[42rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId === null ? 'Crear stock' : 'Editar stock' }}
                </flux:heading>
                <flux:text class="mt-2">
                    Completa los datos de identificación y ubicación del stock.
                </flux:text>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input
                    wire:model="descripcion"
                    label="Descripción"
                    maxlength="180"
                    class="sm:col-span-2"
                />
                <flux:input
                    wire:model="telefono"
                    label="Teléfono"
                    maxlength="20"
                />
                <x-remote-select source="stock-states" cache="states" wire:model="estado" label="Estado" placeholder="Selecciona un estado" />
                <flux:textarea
                    wire:model="ubicacion"
                    label="Ubicación"
                    maxlength="180"
                    rows="3"
                    class="sm:col-span-2"
                />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeForm">
                    Cancelar
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingId === null ? 'Crear stock' : 'Guardar cambios' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Eliminar stock</flux:heading>
                <flux:text class="mt-2">
                    ¿Deseas eliminar <strong>{{ $deletingName }}</strong>? Esta acción no se puede deshacer.
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelDelete">
                    Cancelar
                </flux:button>
                <flux:button wire:click="delete" variant="danger" icon="trash">
                    Eliminar
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
