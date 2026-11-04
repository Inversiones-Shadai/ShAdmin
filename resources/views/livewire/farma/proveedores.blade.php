<div class="farma-screen">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400">Farma</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">Proveedores</h1>
        </div>

        @if ($access['createProviders'])
            <flux:button wire:click="openCreate" variant="primary" icon="plus" data-test="create-provider-button">
                Nuevo proveedor
            </flux:button>
        @endif
    </section>

    @if ($loadError)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="No fue posible completar la solicitud">
            {{ $loadError }}
        </flux:callout>
    @endif

    <section class="farma-table-panel overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div>
                <h2 class="font-semibold text-slate-950 dark:text-white">Listado de proveedores</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ count($visibleProviders) }} en esta página · {{ $total }} en total
                </p>
            </div>

            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Buscar proveedor..."
                class="w-full sm:max-w-xs"
            />
        </div>

        @if ($visibleProviders === [])
            <div class="px-6 py-14 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300">
                    <flux:icon.building-storefront class="size-6" />
                </div>
                <h3 class="mt-4 font-medium text-slate-950 dark:text-white">No hay proveedores para mostrar</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $search !== '' ? 'Prueba con otro término de búsqueda.' : 'Crea el primer proveedor para comenzar.' }}
                </p>
            </div>
        @else
            <div data-test="providers-table-container" class="farma-table-scroll overflow-x-auto px-4 pb-4 pt-2 sm:px-6" tabindex="0">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="py-3.5">ID</flux:table.column>
                        <flux:table.column class="py-3.5">Nombre</flux:table.column>
                        <flux:table.column class="py-3.5">Teléfono</flux:table.column>
                        <flux:table.column class="py-3.5">Dirección</flux:table.column>
                        <flux:table.column class="py-3.5" align="end">Acciones</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($visibleProviders as $provider)
                            <flux:table.row :key="$provider['id']">
                                <flux:table.cell class="py-4 font-mono text-xs">{{ $provider['id'] }}</flux:table.cell>
                                <flux:table.cell class="min-w-48 py-4" variant="strong">
                                    {{ $provider['nombre'] ?: '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="min-w-36 py-4">{{ $provider['telefono'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="min-w-64 max-w-md py-4">
                                    <span class="line-clamp-2">{{ $provider['direccion'] ?: '—' }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="py-4" align="end">
                                    <div class="flex justify-end gap-1">
                                        @if ($access['viewProviderImages'] && $provider['tiene_imagen'])
                                            <flux:button
                                                wire:click="viewImage({{ $provider['id'] }})"
                                                data-image-viewer-key="providers:{{ $provider['id'] }}"
                                                data-image-viewer-src="{{ $provider['imagen_url'] ?? '' }}"
                                                data-image-viewer-name="{{ $provider['nombre'] }}"
                                                icon="photo"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Ver imagen"
                                                data-test="view-provider-image-button"
                                            />
                                        @endif
                                        @if ($access['editProviders'])
                                            <flux:button
                                                wire:click="openEdit({{ $provider['id'] }})"
                                                icon="pencil-square"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Editar proveedor"
                                                data-test="edit-provider-button"
                                            />
                                        @endif
                                        @if ($access['deleteProviders'])
                                            <flux:button
                                                wire:click="confirmDelete({{ $provider['id'] }})"
                                                icon="trash"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Eliminar proveedor"
                                                data-test="delete-provider-button"
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
            <nav class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-700" aria-label="Paginación de proveedores">
                <p class="text-sm text-slate-500 dark:text-slate-400">Página {{ $page }} de {{ $lastPage }}</p>
                <div class="flex items-center gap-2">
                    <flux:button wire:click="previousPage" wire:loading.attr="disabled" icon="chevron-left" size="sm" :disabled="$page <= 1">
                        Anterior
                    </flux:button>
                    <flux:button wire:click="nextPage" wire:loading.attr="disabled" icon-trailing="chevron-right" size="sm" :disabled="$page >= $lastPage">
                        Siguiente
                    </flux:button>
                </div>
            </nav>
        @endif
    </section>

    <flux:modal wire:model.self="showFormModal" class="w-full md:w-176">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId === null ? 'Crear proveedor' : 'Editar proveedor' }}</flux:heading>
                <flux:text class="mt-2">Completa los datos y agrega opcionalmente una imagen JPEG o PNG.</flux:text>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="nombre" label="Nombre" maxlength="180" class="sm:col-span-2" />
                <flux:input wire:model="telefono" label="Teléfono" maxlength="20"/>
                <div class="sm:row-span-2">
                    <label for="provider-image" class="mb-2 block text-sm font-medium text-slate-800 dark:text-slate-200">
                        Imagen
                    </label>
                    <input
                        id="provider-image"
                        type="file"
                        wire:model="imagenArchivo"
                        x-on:change.capture="const file = $event.target.files?.[0]; if (file && file.size > 2097152) { $event.stopImmediatePropagation(); $event.target.value = ''; $wire.rejectOversizedImage(); }"
                        accept="image/jpeg,image/png"
                        class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:font-medium dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:file:bg-slate-800"
                    />
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                        JPEG o PNG, máximo 2 MB. Se optimiza como PNG lossless siempre que sea posible.
                    </p>
                    @error('imagenArchivo')
                        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <flux:textarea wire:model="direccion" label="Dirección" maxlength="255" rows="4" />
            </div>

            @if ($imagenBase64 && $imagenMime)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900 dark:bg-emerald-950/40">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Nueva imagen saneada</p>
                    <img src="data:{{ $imagenMime }};base64,{{ $imagenBase64 }}" alt="Vista previa de la nueva imagen" class="h-28 w-40 rounded-lg bg-white object-contain shadow-sm" />
                </div>
            @elseif ($imagenExistente)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Imagen actual</p>
                    <img src="{{ $imagenExistente }}" alt="Imagen actual del proveedor" class="h-28 w-40 rounded-lg bg-white object-contain shadow-sm" />
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeForm">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ $editingId === null ? 'Crear proveedor' : 'Guardar cambios' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-120">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Eliminar proveedor</flux:heading>
                <flux:text class="mt-2">
                    ¿Deseas eliminar <strong>{{ $deletingName }}</strong>? Esta acción no se puede deshacer.
                </flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelDelete">Cancelar</flux:button>
                <flux:button wire:click="delete" variant="danger" icon="trash">Eliminar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
