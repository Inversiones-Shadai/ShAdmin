<div class="space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">Vistas</h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($access['filterViews'])
                <flux:button wire:click="openModuleFilter" icon="funnel" data-test="filter-views-button">
                    Filtrar por módulo
                </flux:button>
            @endif
            @if ($access['createViews'])
                <flux:button wire:click="openCreate" variant="primary" icon="plus" data-test="create-view-button">
                    Nueva vista
                </flux:button>
            @endif
        </div>
    </section>

    @if ($showModuleFilter)
        <section class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end dark:border-slate-800 dark:bg-slate-900">
            <x-remote-select source="view-modules" cache="modules" wire:model.live="filterModule"
                label="Módulo"
                placeholder="Todos los módulos"
                class="w-full sm:max-w-sm" />
            <flux:button wire:click="clearModuleFilter" icon="x-mark">
                Limpiar filtro
            </flux:button>
        </section>
    @endif

    @if ($loadError)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="No fue posible completar la solicitud">
            {{ $loadError }}
        </flux:callout>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div>
                <h2 class="font-semibold text-slate-950 dark:text-white">Listado de vistas</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ count($visibleViews) }} en esta página · {{ $total }} en total
                </p>
            </div>

            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Buscar vista..."
                class="w-full sm:max-w-xs"
            />
        </div>

        @if ($visibleViews === [])
            <div class="px-6 py-14 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    <flux:icon.vista class="size-6" />
                </div>
                <h3 class="mt-4 font-medium text-slate-950 dark:text-white">No hay vistas para mostrar</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $search !== '' ? 'Prueba con otro término de búsqueda.' : 'Crea la primera vista para comenzar.' }}
                </p>
            </div>
        @else
            <div data-test="views-table-container" class="overflow-x-auto px-4 pb-4 pt-2 sm:px-6">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="py-3.5">ID</flux:table.column>
                        <flux:table.column class="py-3.5">Módulo</flux:table.column>
                        <flux:table.column class="py-3.5">Nombre</flux:table.column>
                        <flux:table.column class="py-3.5">Código</flux:table.column>
                        <flux:table.column class="py-3.5">Estado</flux:table.column>
                        <flux:table.column class="py-3.5" align="end">Acciones</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($visibleViews as $view)
                            <flux:table.row :key="$view['id']">
                                <flux:table.cell class="py-4 font-mono text-xs">{{ $view['id'] }}</flux:table.cell>
                                <flux:table.cell class="py-4">{{ $view['module_label'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="py-4" variant="strong">{{ $view['nombre'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="py-4">
                                    <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                        {{ $view['codigo'] ?: '—' }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => strtoupper(trim($view['state_label'])) === 'ACTIVO',
                                        'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300' => strtoupper(trim($view['state_label'])) === 'INACTIVO',
                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! in_array(strtoupper(trim($view['state_label'])), ['ACTIVO', 'INACTIVO'], true),
                                    ])>
                                        {{ $view['state_label'] ?: 'Sin estado' }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="py-4" align="end">
                                    <div class="flex justify-end gap-1">
                                        @if ($access['manageActions'])
                                            <flux:button
                                                wire:click="openActions({{ $view['id'] }})"
                                                icon="cursor-arrow-rays"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Gestionar acciones"
                                                data-test="manage-view-actions-button"
                                            />
                                        @endif
                                        @if ($access['editViews'])
                                            <flux:button
                                                wire:click="openEdit({{ $view['id'] }})"
                                                icon="pencil-square"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Editar vista"
                                                data-test="edit-view-button"
                                            />
                                        @endif
                                        @if ($access['deleteViews'])
                                            <flux:button
                                                wire:click="confirmDelete({{ $view['id'] }})"
                                                icon="trash"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Eliminar vista"
                                                data-test="delete-view-button"
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

        @if ($lastPage > 1 && $filterModule === '')
            <nav class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-700" aria-label="Paginación de vistas">
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

    <flux:modal wire:model.self="showFormModal" class="w-full md:w-[42rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId === null ? 'Crear vista' : 'Editar vista' }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ $editingId === null
                        ? 'Define el módulo, nombre, código y estado de la nueva vista.'
                        : 'La API permite modificar el estado de la vista seleccionada.' }}
                </flux:text>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-remote-select source="view-modules" cache="modules" wire:model="modulo"
                    label="Módulo"
                    placeholder="Selecciona un módulo"
                    class="sm:col-span-2"
                    :disabled="$editingId !== null" />

                <flux:input wire:model="nombre" label="Nombre" :disabled="$editingId !== null" />
                <flux:input
                    wire:model="codigo"
                    label="Código"
                    description="{{ $editingId === null ? 'Máximo 10 caracteres; se almacenará en minúsculas.' : '' }}"
                    :disabled="$editingId !== null"
                />

                <x-remote-select source="view-states" cache="states" wire:model="estado" label="Estado" placeholder="Selecciona un estado" class="sm:col-span-2" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeForm">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingId === null ? 'Crear vista' : 'Guardar cambios' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showActionsModal" class="w-full md:w-[64rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Acciones de {{ $actionsViewName }}</flux:heading>
                <flux:text class="mt-2">
                    Administra las operaciones y los códigos de botón que pertenecen a esta vista.
                </flux:text>
            </div>

            @if ($access['createActions'])
                <form wire:submit="saveAction" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-test="create-view-action-form">
                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input wire:model="actionName" label="Nombre de la acción" placeholder="Ej. crear" />
                        <flux:input
                            wire:model="actionCode"
                            label="Código del botón"
                            placeholder="Ej. vsbtn01"
                            description="Máximo 20 caracteres; se almacenará en minúsculas."
                        />
                    </div>
                    <div class="mt-4 flex justify-end">
                        <flux:button type="submit" variant="primary" icon="plus">
                            Agregar acción
                        </flux:button>
                    </div>
                </form>
            @endif

            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <h3 class="font-medium text-slate-950 dark:text-white">Acciones registradas</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ count($actions) }} {{ count($actions) === 1 ? 'acción' : 'acciones' }}
                    </p>
                </div>

                @if ($actions === [])
                    <div class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                        Esta vista todavía no tiene acciones registradas.
                    </div>
                @else
                    <div data-test="view-actions-table-container" class="overflow-x-auto px-4 pb-4 pt-2 sm:px-6">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column class="py-3.5">ID</flux:table.column>
                                <flux:table.column class="py-3.5">Nombre</flux:table.column>
                                <flux:table.column class="py-3.5">Código del botón</flux:table.column>
                                <flux:table.column class="py-3.5" align="end">Acciones</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($actions as $action)
                                    <flux:table.row :key="$action['id']">
                                        <flux:table.cell class="py-4 font-mono text-xs">{{ $action['id'] }}</flux:table.cell>
                                        <flux:table.cell class="py-4" variant="strong">{{ $action['nombre'] ?: '—' }}</flux:table.cell>
                                        <flux:table.cell class="py-4">
                                            <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                                {{ $action['codigo'] ?: '—' }}
                                            </span>
                                        </flux:table.cell>
                                        <flux:table.cell class="py-4" align="end">
                                            @if ($access['deleteActions'])
                                                <flux:button
                                                    wire:click="confirmActionDelete({{ $action['id'] }})"
                                                    icon="trash"
                                                    variant="ghost"
                                                    size="sm"
                                                    square
                                                    tooltip="Eliminar acción"
                                                    data-test="delete-view-action-button"
                                                />
                                            @endif
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif
            </div>

            <div class="flex justify-end">
                <flux:button wire:click="closeActions">Cerrar</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showActionDeleteModal" class="md:w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Eliminar acción</flux:heading>
                <flux:text class="mt-2">
                    ¿Deseas eliminar <strong>{{ $deletingActionName }}</strong>?
                </flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelActionDelete">Cancelar</flux:button>
                <flux:button wire:click="deleteAction" variant="danger" icon="trash">Eliminar acción</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Eliminar vista</flux:heading>
                <flux:text class="mt-2">
                    ¿Deseas eliminar <strong>{{ $deletingName }}</strong>? Esta acción no se puede deshacer.
                </flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelDelete">Cancelar</flux:button>
                <flux:button wire:click="delete" variant="danger" icon="trash">Eliminar vista</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
