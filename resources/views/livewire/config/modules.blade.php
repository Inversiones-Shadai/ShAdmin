<div class="space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">Módulos</h1>
        </div>

        @if ($access['createModules'])
            <flux:button wire:click="openCreate" variant="primary" icon="plus" data-test="create-module-button">
                Nuevo módulo
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
                <h2 class="font-semibold text-slate-950 dark:text-white">Listado de módulos</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ count($visibleModules) }} en esta página · {{ $total }} en total
                </p>
            </div>

            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Buscar módulo..."
                class="w-full sm:max-w-xs"
            />
        </div>

        @if ($visibleModules === [])
            <div class="px-6 py-14 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    <flux:icon.modulos class="size-6" />
                </div>
                <h3 class="mt-4 font-medium text-slate-950 dark:text-white">No hay módulos para mostrar</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $search !== '' ? 'Prueba con otro término de búsqueda.' : 'Crea el primer módulo para comenzar.' }}
                </p>
            </div>
        @else
            <div data-test="modules-table-container" class="overflow-x-auto px-4 pb-4 pt-2 sm:px-6">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="py-3.5">ID</flux:table.column>
                        <flux:table.column class="py-3.5">Nombre</flux:table.column>
                        <flux:table.column class="py-3.5">Código</flux:table.column>
                        <flux:table.column class="py-3.5">Estado</flux:table.column>
                        <flux:table.column class="py-3.5" align="end">Acciones</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($visibleModules as $module)
                            <flux:table.row :key="$module['id']">
                                <flux:table.cell class="py-4 font-mono text-xs">{{ $module['id'] }}</flux:table.cell>
                                <flux:table.cell class="py-4" variant="strong">{{ $module['nombre'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="py-4">
                                    <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                        {{ $module['codigo'] ?: '—' }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => strtoupper(trim($module['state_label'])) === 'ACTIVO',
                                        'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300' => strtoupper(trim($module['state_label'])) === 'INACTIVO',
                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! in_array(strtoupper(trim($module['state_label'])), ['ACTIVO', 'INACTIVO'], true),
                                    ])>
                                        {{ $module['state_label'] ?: 'Sin estado' }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="py-4" align="end">
                                    <div class="flex justify-end gap-1">
                                        @if ($access['editModules'])
                                            <flux:button
                                                wire:click="openEdit({{ $module['id'] }})"
                                                icon="pencil-square"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Editar módulo"
                                                data-test="edit-module-button"
                                            />
                                        @endif
                                        @if ($access['deleteModules'])
                                            <flux:button
                                                wire:click="confirmDelete({{ $module['id'] }})"
                                                icon="trash"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Eliminar módulo"
                                                data-test="delete-module-button"
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
            <nav class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-700" aria-label="Paginación de módulos">
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

    <flux:modal wire:model.self="showFormModal" class="w-full md:w-[38rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId === null ? 'Crear módulo' : 'Editar módulo' }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ $editingId === null
                        ? 'Define el nombre, código y estado del nuevo módulo.'
                        : 'La API permite modificar el estado del módulo seleccionado.' }}
                </flux:text>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input
                    wire:model="nombre"
                    label="Nombre"
                    :disabled="$editingId !== null"
                />
                <flux:input
                    wire:model="codigo"
                    label="Código"
                    description="{{ $editingId === null ? 'Se almacenará en minúsculas.' : '' }}"
                    :disabled="$editingId !== null"
                />

                <x-remote-select source="module-states" cache="states" wire:model="estado" label="Estado" placeholder="Selecciona un estado" class="sm:col-span-2" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeForm">
                    Cancelar
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingId === null ? 'Crear módulo' : 'Guardar cambios' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Eliminar módulo</flux:heading>
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
