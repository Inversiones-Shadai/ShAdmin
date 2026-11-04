<div class="space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Sorteos</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">Lista de sorteos</h1>
        </div>

        @if ($access['createDraws'])
            <flux:button wire:click="openCreate" variant="primary" icon="plus" data-test="create-draw-button">
                Nuevo sorteo
            </flux:button>
        @endif
    </section>

    @if ($loadError)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="No fue posible completar la solicitud">
            {{ $loadError }}
        </flux:callout>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-4 lg:flex-row lg:items-end lg:justify-between dark:border-slate-800">
            <div>
                <h2 class="font-semibold text-slate-950 dark:text-white">Sorteos registrados</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ count($draws) }} en esta página · {{ $total }} en total
                </p>
            </div>

            @if ($access['searchDraws'])
                <form wire:submit="applyFilter" class="flex w-full flex-col gap-2 sm:flex-row lg:max-w-xl">
                    <flux:input
                        wire:model.live.debounce.350ms="filterTerm"
                        icon="magnifying-glass"
                        placeholder="Buscar por nombre o descripción..."
                        class="w-full"
                    />
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="primary">
                            Buscar
                        </flux:button>
                        @if ($filterActive)
                            <flux:button type="button" wire:click="clearFilter">
                                Limpiar
                            </flux:button>
                        @endif
                    </div>
                </form>
            @endif
        </div>

        @if ($filterActive)
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-300">
                Resultados para <strong>{{ $filterTerm }}</strong>
            </div>
        @endif

        @if ($draws === [])
            <div class="px-6 py-14 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    <flux:icon.sparkles class="size-6" />
                </div>
                <h3 class="mt-4 font-medium text-slate-950 dark:text-white">No hay sorteos para mostrar</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $filterActive ? 'No se encontraron coincidencias.' : 'Crea el primer sorteo para comenzar.' }}
                </p>
            </div>
        @else
            <div data-test="draws-table-container" class="overflow-x-auto px-4 pb-4 pt-2 sm:px-6">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="py-3.5">ID</flux:table.column>
                        <flux:table.column class="py-3.5">Sorteo</flux:table.column>
                        <flux:table.column class="py-3.5">Periodo</flux:table.column>
                        <flux:table.column class="py-3.5">Impresiones</flux:table.column>
                        <flux:table.column class="py-3.5">Valor</flux:table.column>
                        <flux:table.column class="py-3.5">Estado</flux:table.column>
                        <flux:table.column class="py-3.5" align="end">Acciones</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($draws as $draw)
                            <flux:table.row :key="$draw['id']">
                                <flux:table.cell class="py-4 font-mono text-xs">{{ $draw['id'] }}</flux:table.cell>
                                <flux:table.cell class="min-w-64 py-4">
                                    <div class="font-medium text-slate-950 dark:text-white">{{ $draw['nombre'] ?: '—' }}</div>
                                    <div class="mt-1 max-w-80 truncate text-sm text-slate-500 dark:text-slate-400">
                                        {{ $draw['descripcion'] ?: 'Sin descripción' }}
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="min-w-48 py-4">
                                    <div class="text-sm">{{ $draw['fecha_inicio'] ?: '—' }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        hasta {{ $draw['fecha_fin'] ?: '—' }}
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="py-4">
                                    {{ number_format($draw['impresiones']) }}
                                </flux:table.cell>
                                <flux:table.cell class="py-4">
                                    L {{ number_format($draw['valor'], 2) }}
                                </flux:table.cell>
                                <flux:table.cell class="py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => strtoupper(trim($draw['state_label'])) === 'ACTIVO',
                                        'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300' => strtoupper(trim($draw['state_label'])) === 'INACTIVO',
                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! in_array(strtoupper(trim($draw['state_label'])), ['ACTIVO', 'INACTIVO'], true),
                                    ])>
                                        {{ $draw['state_label'] ?: 'Sin estado' }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="py-4" align="end">
                                    <div class="flex justify-end gap-1">
                                        @if ($access['editDraws'])
                                            <flux:button
                                                wire:click="openEdit({{ $draw['id'] }})"
                                                icon="pencil-square"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Editar sorteo"
                                                data-test="edit-draw-button"
                                            />
                                        @endif
                                        @if ($access['deleteDraws'])
                                            <flux:button
                                                wire:click="confirmDelete({{ $draw['id'] }})"
                                                icon="trash"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Eliminar sorteo"
                                                data-test="delete-draw-button"
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
            <nav class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-700" aria-label="Paginación de sorteos">
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

    <flux:modal wire:model.self="showFormModal" class="w-full md:w-[46rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId === null ? 'Crear sorteo' : 'Editar sorteo' }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ $editingId === null
                        ? 'Define los datos, vigencia y estado del nuevo sorteo.'
                        : 'La API conserva las fechas originales al actualizar.' }}
                </flux:text>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input
                    wire:model="nombre"
                    label="Nombre"
                    maxlength="200"
                    class="sm:col-span-2"
                />
                <flux:textarea
                    wire:model="descripcion"
                    label="Descripción"
                    maxlength="200"
                    rows="3"
                    class="sm:col-span-2"
                />
                <flux:input
                    wire:model="fechaInicio"
                    type="date"
                    label="Fecha de inicio"
                    :disabled="$editingId !== null"
                />
                <flux:input
                    wire:model="fechaFin"
                    type="date"
                    label="Fecha de finalización"
                    :disabled="$editingId !== null"
                />
                <flux:input
                    wire:model="impresiones"
                    type="number"
                    min="0"
                    step="1"
                    label="Impresiones"
                />
                <flux:input
                    wire:model="valor"
                    type="number"
                    min="0"
                    step="0.01"
                    label="Valor"
                    prefix="L"
                />
                <x-remote-select source="draw-states" cache="states" wire:model="estado" label="Estado" placeholder="Selecciona un estado" class="sm:col-span-2" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeForm">
                    Cancelar
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingId === null ? 'Crear sorteo' : 'Guardar cambios' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Eliminar sorteo</flux:heading>
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
