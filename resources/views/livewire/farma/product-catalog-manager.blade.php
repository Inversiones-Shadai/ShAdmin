<div class="farma-screen">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400">Farma · Productos</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">{{ $definition['label'] }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">{{ $definition['description'] }}</p>
        </div>

        @if ($access['create'])
            <flux:button wire:click="openCreate" variant="primary" icon="plus">
                Nuevo registro
            </flux:button>
        @endif
    </header>

    <section class="farma-table-panel overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div>
                <h2 class="font-semibold text-slate-900 dark:text-white">Registros</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ number_format($total) }} en total</p>
            </div>
            <flux:input
                wire:model.live.debounce.250ms="search"
                icon="magnifying-glass"
                placeholder="Buscar en esta página"
                class="w-full sm:max-w-xs"
            />
        </div>

        @if ($loadError)
            <div class="m-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                {{ $loadError }}
            </div>
        @elseif ($visibleRecords === [])
            <div class="grid min-h-52 place-items-center p-8 text-center">
                <div>
                    <flux:icon :name="$definition['icon']" class="mx-auto size-10 text-slate-300 dark:text-slate-600" />
                    <p class="mt-3 font-medium text-slate-700 dark:text-slate-200">No hay registros para mostrar</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Crea el primer registro o cambia la búsqueda.</p>
                </div>
            </div>
        @else
            <div data-test="product-catalog-table-container" class="farma-table-scroll product-catalog-table-container overflow-x-auto px-4 pb-4 pt-2 sm:px-6" tabindex="0">
                <flux:table class="w-full min-w-3xl">
                    <flux:table.columns>
                        <flux:table.column class="w-24 py-3.5">ID</flux:table.column>
                        @foreach ($definition['columns'] as $column)
                            <flux:table.column class="min-w-48 py-3.5 {{ $catalog === 'familias' && $loop->first ? 'w-2/5' : '' }}">{{ $column['label'] }}</flux:table.column>
                        @endforeach
                        <flux:table.column class="w-28 py-3.5" align="end">Acciones</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($visibleRecords as $record)
                            <flux:table.row :key="$catalog.'-'.$record['id']">
                                <flux:table.cell class="py-4 font-mono text-xs">{{ $record['id'] }}</flux:table.cell>
                                @foreach ($definition['columns'] as $column)
                                    @php($value = data_get($record, $column['key']))
                                    <flux:table.cell class="min-w-48 py-4 align-top leading-6" :variant="$loop->first ? 'strong' : null">
                                        @if ($catalog === 'estados' && $column['key'] === 'descripcion')
                                            <x-product-status :status="$value" />
                                        @else
                                            {{ filled($value) ? $value : '—' }}
                                        @endif
                                    </flux:table.cell>
                                @endforeach
                                <flux:table.cell class="py-4" align="end">
                                    <div class="flex justify-end gap-1">
                                        @if ($access['edit'])
                                            <flux:button
                                                wire:click="openEdit({{ $record['id'] }})"
                                                icon="pencil-square"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Editar"
                                                data-test="edit-product-catalog-button"
                                            />
                                        @endif
                                        @if ($access['delete'])
                                            <flux:button
                                                wire:click="confirmDelete({{ $record['id'] }})"
                                                icon="trash"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Eliminar"
                                                data-test="delete-product-catalog-button"
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
            <nav class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-800" aria-label="Paginación">
                <p class="text-sm text-slate-500 dark:text-slate-400">Página {{ $page }} de {{ $lastPage }}</p>
                <div class="flex items-center gap-2">
                    <flux:button wire:click="previousPage" icon="chevron-left" size="sm" :disabled="$page <= 1">Anterior</flux:button>
                    <flux:button wire:click="nextPage" icon-trailing="chevron-right" size="sm" :disabled="$page >= $lastPage">Siguiente</flux:button>
                </div>
            </nav>
        @endif
    </section>

    <flux:modal wire:model.self="showFormModal" class="w-full {{ $catalog === 'familias' ? 'max-w-[calc(100vw-2rem)] md:w-280' : ($catalog === 'principios-activos' ? 'md:w-184' : 'md:w-160') }}">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId === null ? 'Crear' : 'Editar' }} {{ $definition['singular'] }}
                </flux:heading>
                <flux:text class="mt-2">{{ $definition['description'] }}</flux:text>
            </div>

            <div class="grid gap-5 {{ $catalog === 'familias' ? 'lg:grid-cols-2' : 'sm:grid-cols-2' }}">
                @foreach ($definition['fields'] as $field)
                    @if ($field['type'] === 'select')
                        <x-remote-select
                            :source="match ($field['options_catalog']) { 'presentaciones' => 'family-presentations', 'administraciones' => 'family-administrations', 'concentraciones' => 'active-concentrations' }"
                            cache="options.{{ $field['name'] }}" wire:model="form.{{ $field['name'] }}"
                            :label="$field['label']" placeholder="Selecciona una opción" />
                    @else
                        <flux:input
                            wire:model="form.{{ $field['name'] }}"
                            :type="$field['type'] === 'number' ? 'number' : 'text'"
                            :label="$field['label']"
                            :maxlength="$field['max'] ?? null"
                            :min="$field['min'] ?? null"
                            class="{{ $catalog === 'familias' ? 'lg:col-span-2' : ($catalog === 'principios-activos' || count($definition['fields']) === 1 ? 'sm:col-span-2' : '') }}"
                        />
                    @endif
                @endforeach
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeForm">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ $editingId === null ? 'Crear registro' : 'Guardar cambios' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-120">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Eliminar {{ $definition['singular'] }}</flux:heading>
                <flux:text class="mt-2">
                    ¿Deseas eliminar <strong>{{ $deletingName }}</strong>? No será posible si está relacionado con otros datos.
                </flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelDelete">Cancelar</flux:button>
                <flux:button wire:click="delete" variant="danger" icon="trash">Eliminar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
