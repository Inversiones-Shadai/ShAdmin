<div class="farma-screen">
    <style>
        .products-form-modal {
            width: calc(100vw - 2rem) !important;
            max-width: calc(100vw - 2rem) !important;
        }

        .products-family-picker-modal {
            width: min(60rem, calc(100vw - 2rem)) !important;
            max-width: calc(100vw - 2rem) !important;
        }

        @media (min-width: 64rem) {
            dialog.products-content-modal {
                inset-inline-start: 17rem !important;
            }

            .products-form-modal {
                width: calc(100vw - 19rem) !important;
                max-width: calc(100vw - 19rem) !important;
            }

            .products-family-picker-modal {
                width: min(60rem, calc(100vw - 19rem)) !important;
                max-width: calc(100vw - 19rem) !important;
            }
        }
    </style>

    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="mb-2 flex items-center gap-2 text-sm font-medium text-cyan-700 dark:text-cyan-300">
                <flux:icon.cube class="size-4" />
                Farma
            </div>
            <flux:heading size="xl">Productos</flux:heading>
            <flux:text class="mt-2 max-w-2xl">
                Administra el catálogo comercial, sus clasificaciones, unidades y principios activos.
            </flux:text>
        </div>

        @if ($access['create'])
            <flux:button wire:click="openCreate" variant="primary" icon="plus" data-test="create-product-button">
                Nuevo producto
            </flux:button>
        @endif
    </header>

    <section class="farma-table-panel overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div>
                <h2 class="font-semibold text-slate-900 dark:text-white">Catálogo</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ number_format($total) }} productos registrados</p>
            </div>
            <flux:input
                wire:model.live.debounce.250ms="search"
                icon="magnifying-glass"
                placeholder="Código, descripción, laboratorio…"
                class="w-full sm:max-w-sm"
            />
        </div>

        @if ($loadError)
            <div class="m-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                {{ $loadError }}
            </div>
        @elseif ($visibleProducts === [])
            <div class="grid min-h-56 place-items-center p-8 text-center">
                <div>
                    <span class="mx-auto grid size-12 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                        <flux:icon.cube class="size-6" />
                    </span>
                    <p class="mt-3 font-medium text-slate-700 dark:text-slate-200">No hay productos para mostrar</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Crea el primer producto o cambia la búsqueda.</p>
                </div>
            </div>
        @else
            <div data-test="product-table-container" class="farma-table-scroll product-table-container px-4 pb-4 pt-2 sm:px-6" tabindex="0">
                <flux:table class="w-full bg-white dark:bg-slate-900">
                    <flux:table.columns>
                        <flux:table.column class="w-[7%] py-3.5">Código</flux:table.column>
                        <flux:table.column class="w-[27%] py-3.5">Producto</flux:table.column>
                        <flux:table.column class="w-[13%] py-3.5">Laboratorio</flux:table.column>
                        <flux:table.column class="w-[10%] py-3.5">Categoría</flux:table.column>
                        <flux:table.column class="w-[14%] py-3.5">Familia / unidad</flux:table.column>
                        <flux:table.column class="w-[14%] py-3.5">Principios activos</flux:table.column>
                        <flux:table.column class="w-[7%] py-3.5">Estado</flux:table.column>
                        <flux:table.column class="w-[8%] py-3.5" align="end">Acciones</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($visibleProducts as $product)
                            <flux:table.row :key="'product-'.$product['id']">
                                <flux:table.cell class="py-4" data-label="Código">
                                    <span class="font-mono text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        {{ $product['codigo'] }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="py-4" variant="strong" data-label="Producto">
                                    <div class="flex items-center gap-3">
                                        @if ($product['tiene_imagen'] && $access['image'])
                                            <button
                                                type="button"
                                                wire:click="viewImage({{ $product['id'] }})"
                                                data-image-viewer-key="products:{{ $product['id'] }}"
                                                data-image-viewer-src="{{ $product['imagen_url'] ?? '' }}"
                                                data-image-viewer-on-demand
                                                data-image-viewer-fallback-src="{{ route('farma.products.preview', $product['id']) }}"
                                                data-image-viewer-name="{{ $product['descripcion'] }}"
                                                class="grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-slate-400 hover:text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-white"
                                                title="Ver imagen"
                                                data-test="view-product-image-button"
                                            >
                                                <flux:icon.photo class="size-5" />
                                            </button>
                                        @else
                                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                                                <flux:icon.cube class="size-5" />
                                            </span>
                                        @endif
                                        <span class="min-w-0 flex-1">
                                            <span class="block whitespace-normal break-words leading-snug [overflow-wrap:anywhere]">{{ $product['descripcion'] }}</span>
                                            <span class="mt-1 block font-mono text-[11px] text-slate-400">
                                                {{ $product['codigobar'] !== '' ? $product['codigobar'] : 'Sin código de barras' }}
                                            </span>
                                        </span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="py-4" data-label="Laboratorio">
                                    <span
                                        class="block whitespace-normal break-words leading-snug [overflow-wrap:anywhere]"
                                        title="{{ $product['laboratorio_label'] !== '' ? $product['laboratorio_label'] : 'Sin laboratorio' }}"
                                        data-test="product-laboratory"
                                    >
                                        {{ $product['laboratorio_label'] !== '' ? $product['laboratorio_label'] : '—' }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="py-4" data-label="Categoría">
                                    {{ $product['categoria_label'] !== '' ? $product['categoria_label'] : '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="py-4" data-label="Familia / unidad">
                                    <span class="block font-medium text-slate-700 dark:text-slate-200">{{ $product['familia_label'] !== '' ? $product['familia_label'] : '—' }}</span>
                                    @if ($product['familia_presentacion_label'] !== '')
                                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                                            Presentación: {{ $product['familia_presentacion_label'] }}
                                        </span>
                                    @endif
                                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                                        {{ $product['unidad_label'] !== '' ? $product['unidad_label'] : 'Sin unidad' }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="py-4" data-label="Principios activos">
                                    @if ($product['principios_activos_labels'] !== [])
                                        <div class="flex max-w-64 flex-wrap gap-1">
                                            @foreach (array_slice($product['principios_activos_labels'], 0, 2) as $active)
                                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                                    {{ $active }}
                                                </span>
                                            @endforeach
                                            @if (count($product['principios_activos_labels']) > 2)
                                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                                    +{{ count($product['principios_activos_labels']) - 2 }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="py-4" data-label="Estado">
                                    <x-product-status :status="$product['estado_label']" />
                                </flux:table.cell>
                                <flux:table.cell class="py-4" align="end" data-label="Acciones">
                                    <div class="flex justify-end gap-1">
                                        @if ($access['createChild'] && $product['producto_padre_id'] === '' && ! \Illuminate\Support\Str::endsWith(mb_strtolower(rtrim($product['codigo'])), 'h'))
                                            <flux:button
                                                wire:click="openChild({{ $product['id'] }})"
                                                icon="square-2-stack"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Convertir a hijo"
                                                data-test="create-product-child-button"
                                            />
                                        @endif
                                        @if ($access['edit'])
                                            <flux:button
                                                wire:click="openEdit({{ $product['id'] }})"
                                                icon="pencil-square"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Editar"
                                                data-test="edit-product-button"
                                            />
                                        @endif
                                        @if ($access['delete'])
                                            <flux:button
                                                wire:click="confirmDelete({{ $product['id'] }})"
                                                icon="trash"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Eliminar"
                                                data-test="delete-product-button"
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

    <flux:modal
        wire:key="product-form-modal"
        wire:model.self="showFormModal"
        class="products-content-modal products-form-modal bg-white! dark:bg-slate-900!"
    >
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId === null ? 'Crear producto' : 'Editar producto' }}</flux:heading>
                <flux:text class="mt-2">Completa la identificación comercial y clasificación farmacéutica.</flux:text>
            </div>

            <div class="grid items-start gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <flux:input wire:model="codigo" label="Código" maxlength="20" />
                <flux:input wire:model="codigobar" label="Código de barras" maxlength="50" />
                <flux:input wire:model="descripcion" label="Descripción" maxlength="150" class="sm:col-span-2" />

                <div class="sm:col-span-2 lg:col-span-4" data-test="product-parent-field">
                    <span class="mb-2 block text-sm font-medium">Producto padre</span>
                    <div class="flex items-center gap-2 rounded-lg border border-slate-200 p-2 dark:border-slate-700">
                        <span class="min-w-0 flex-1 break-words text-sm">{{ $parentName !== '' ? $parentName : 'Sin producto padre' }}</span>
                        <flux:button type="button" wire:click="openParentPicker" icon="magnifying-glass" size="sm">Seleccionar</flux:button>
                        @if ($productoPadreId !== '')
                            <flux:button type="button" wire:click="clearParent" icon="x-mark" size="sm">Quitar</flux:button>
                        @endif
                    </div>
                    @error('productoPadreId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <x-remote-select source="product-categories" cache="options.categorias" wire:model.live="categoria" label="Categoría" label:class="flex min-h-8 items-center" placeholder="Selecciona una categoría" />
                <div class="min-w-0">
                    <div class="mb-2 flex min-h-8 items-center justify-between gap-3">
                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">Laboratorio</span>
                        <flux:button type="button" wire:click="openLaboratoryPicker" icon="magnifying-glass" size="sm" data-test="open-laboratory-picker">
                            {{ $laboratorio !== '' ? 'Cambiar' : 'Seleccionar' }}
                        </flux:button>
                    </div>
                    <div class="flex min-h-10 items-center rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        <span class="min-w-0 flex-1 break-words text-sm text-slate-800 dark:text-slate-100" data-test="selected-laboratory">{{ $laboratoryName !== '' ? $laboratoryName : 'Sin seleccionar' }}</span>
                    </div>
                    @error('laboratorio')
                        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="min-w-0 sm:col-span-2 lg:col-span-2">
                    <div class="mb-2 flex min-h-8 items-center justify-between gap-3">
                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">Familia{{ $familyRequired ? ' (obligatoria)' : '' }}</span>
                        <div class="flex flex-wrap gap-2">
                            <flux:button type="button" wire:click="openFamilyPicker" icon="magnifying-glass" size="sm" data-test="open-family-picker">
                                {{ $selectedFamily ? 'Cambiar' : 'Seleccionar' }}
                            </flux:button>
                            @if ($selectedFamily && ! $familyRequired)
                                <flux:button type="button" wire:click="clearFamily" size="sm">Quitar</flux:button>
                            @endif
                        </div>
                    </div>
                    <div class="grid gap-3 rounded-lg border border-slate-200 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-900 sm:grid-cols-3" data-test="selected-family">
                        <div>
                            <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Familia</span>
                            <span class="mt-1 block font-medium text-slate-800 dark:text-slate-100">{{ $selectedFamily['nombre'] ?? $selectedFamily['label'] ?? 'Sin seleccionar' }}</span>
                        </div>
                        <div>
                            <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Presentación</span>
                            <span class="mt-1 block text-slate-700 dark:text-slate-200">{{ $selectedFamily['presentacion'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Administración</span>
                            <span class="mt-1 block text-slate-700 dark:text-slate-200">{{ $selectedFamily['administracion'] ?? '—' }}</span>
                        </div>
                    </div>
                    @error('familia')
                        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <x-remote-select source="product-units" cache="options.unidades" wire:model="unidad" label="Unidades" />
                <x-remote-select source="product-states" cache="options.estados" wire:model="estado" label="Estado" />
                <div class="sm:col-span-2 lg:col-span-3">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <div>
                            <label class="text-sm font-medium text-slate-800 dark:text-slate-200">Principios activos</label>
                            <p class="text-xs text-slate-500">Principio activo y concentración asociados al producto.</p>
                        </div>
                        <flux:button type="button" wire:click="openActivePicker" variant="primary" icon="plus" size="sm" data-test="open-active-picker">Agregar</flux:button>
                    </div>
                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                        <div class="farma-table-scroll overflow-x-auto" tabindex="0">
                            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700" data-test="selected-actives-table">
                                <thead class="bg-white text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900">
                                    <tr>
                                        <th class="px-3 py-2.5 font-semibold">Principio activo</th>
                                        <th class="px-3 py-2.5 font-semibold">Concentración</th>
                                        <th class="w-20 px-3 py-2.5 text-right font-semibold">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900">
                                    @forelse ($selectedActives['items'] as $active)
                                        <tr wire:key="selected-active-{{ $active['id'] }}">
                                            <td class="px-3 py-2.5 font-medium text-slate-800 dark:text-slate-100">{{ $active['nombre'] ?? $active['label'] }}</td>
                                            <td class="px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ data_get($active, 'concentracion_detalle.valor', '—') }}</td>
                                            <td class="px-3 py-2.5 text-right">
                                                <flux:button type="button" wire:click="removeActive({{ $active['id'] }})" variant="ghost" icon="trash" size="xs" tooltip="Eliminar" />
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-3 py-6 text-center text-slate-500">Aún no has agregado principios activos.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($selectedActives['lastPage'] > 1)
                            <div class="flex items-center justify-between gap-3 border-t border-slate-200 px-3 py-2.5 dark:border-slate-700">
                                <p class="text-xs text-slate-500">{{ $selectedActives['total'] }} agregados · Página {{ $selectedActives['page'] }} de {{ $selectedActives['lastPage'] }}</p>
                                <div class="flex gap-2">
                                    <flux:button type="button" wire:click="previousSelectedActivePage" icon="chevron-left" size="xs" :disabled="$selectedActives['page'] <= 1">Anterior</flux:button>
                                    <flux:button type="button" wire:click="nextSelectedActivePage" icon-trailing="chevron-right" size="xs" :disabled="$selectedActives['page'] >= $selectedActives['lastPage']">Siguiente</flux:button>
                                </div>
                            </div>
                        @endif
                    </div>
                    @error('principiosActivos')
                        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2 lg:col-span-1">
                    <label for="product-image" class="mb-2 block text-sm font-medium text-slate-800 dark:text-slate-200">Imagen</label>
                    <input
                        id="product-image"
                        type="file"
                        wire:model="imagenArchivo"
                        accept="image/jpeg,image/png"
                        class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:font-medium dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:file:bg-slate-800"
                    />
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">JPEG o PNG, máximo 1 MB.</p>
                    @error('imagenArchivo')
                        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    @if ($imagenBase64 && $imagenMime)
                        <div class="mt-3 w-fit max-w-full rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Nueva imagen optimizada</p>
                            <img src="data:{{ $imagenMime }};base64,{{ $imagenBase64 }}" alt="Vista previa del producto" class="h-24 w-36 rounded-md bg-white object-contain" />
                        </div>
                    @elseif ($imagenExistente)
                        <div class="mt-3 w-fit max-w-full rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Imagen actual</p>
                            <img src="{{ $imagenExistente }}" alt="Imagen actual del producto" class="h-24 w-36 rounded-md bg-white object-contain" />
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeForm">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                    {{ $editingId === null ? 'Crear producto' : 'Guardar cambios' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    @if ($showLaboratoryPicker)
    <flux:modal wire:key="product-laboratory-picker-modal" wire:model.self="showLaboratoryPicker" class="products-content-modal w-full bg-white! dark:bg-slate-900! md:w-184">
        <div class="space-y-5" data-test="laboratory-picker-modal">
            <div class="flex flex-wrap items-start justify-between gap-3 pr-6">
                <div>
                    <flux:heading size="lg">Seleccionar laboratorio</flux:heading>
                    <flux:text class="mt-2">Busca por nombre y selecciona un laboratorio.</flux:text>
                </div>
                <flux:button type="button" wire:click="retryLaboratoryPage" icon="arrow-path" size="sm">Actualizar</flux:button>
            </div>
            <flux:input wire:model.live.debounce.350ms="laboratorySearch" label="Buscar laboratorio" icon="magnifying-glass" placeholder="Nombre del laboratorio" maxlength="255" clearable />
            <div wire:loading wire:target="openLaboratoryPicker,laboratorySearch,previousLaboratoryPage,nextLaboratoryPage,retryLaboratoryPage" class="text-sm text-slate-500" role="status">Cargando laboratorios…</div>
            @if ($laboratoryLoadError)
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300" role="alert">
                    <p>{{ $laboratoryLoadError }}</p>
                    <flux:button type="button" wire:click="retryLaboratoryPage" size="sm" class="mt-3">Reintentar</flux:button>
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                    <div class="farma-table-scroll max-h-104 overflow-y-auto" tabindex="0">
                        <table class="w-full table-fixed divide-y divide-slate-200 text-sm dark:divide-slate-700" data-test="laboratory-picker-table">
                            <thead class="sticky top-0 bg-white text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900">
                                <tr>
                                    <th class="px-3 py-3 font-semibold">Nombre</th>
                                    <th class="w-32 px-3 py-3 text-right font-semibold">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900">
                                @forelse ($laboratoryOptions as $laboratoryOption)
                                    <tr wire:key="laboratory-option-{{ $laboratoryOption['id'] }}">
                                        <td class="break-words px-3 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $laboratoryOption['nombre'] }}</td>
                                        <td class="px-3 py-3 text-right">
                                            <flux:button type="button" wire:click="selectLaboratory({{ $laboratoryOption['id'] }})" wire:loading.attr="disabled" wire:target="laboratorySearch,previousLaboratoryPage,nextLaboratoryPage,selectLaboratory" variant="primary" size="sm">Seleccionar</flux:button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="px-3 py-8 text-center text-slate-500">No hay laboratorios que coincidan con la búsqueda.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-3 py-2.5 dark:border-slate-700">
                        <p class="text-xs text-slate-500">{{ $laboratoryTotal }} resultados · Página {{ $laboratoryPage }} de {{ $laboratoryLastPage }}</p>
                        <div class="flex gap-2">
                            <flux:button type="button" wire:click="previousLaboratoryPage" wire:loading.attr="disabled" wire:target="laboratorySearch,previousLaboratoryPage,nextLaboratoryPage" icon="chevron-left" size="sm" :disabled="$laboratoryPage <= 1">Anterior</flux:button>
                            <flux:button type="button" wire:click="nextLaboratoryPage" wire:loading.attr="disabled" wire:target="laboratorySearch,previousLaboratoryPage,nextLaboratoryPage" icon-trailing="chevron-right" size="sm" :disabled="$laboratoryPage >= $laboratoryLastPage">Siguiente</flux:button>
                        </div>
                    </div>
                </div>
            @endif
            <div class="flex justify-end"><flux:button type="button" wire:click="closeLaboratoryPicker">Cerrar</flux:button></div>
        </div>
    </flux:modal>
    @endif

    @if ($showFamilyPicker)
    <flux:modal wire:key="product-family-picker-modal" wire:model.self="showFamilyPicker" class="products-content-modal products-family-picker-modal bg-white! dark:bg-slate-900!">
        <div wire:init="retryFamilyPage" class="space-y-5" data-test="family-picker-modal">
            <div class="flex flex-wrap items-start justify-between gap-3 pr-6">
                <div>
                    <flux:heading size="lg">Seleccionar familia</flux:heading>
                    <flux:text class="mt-2">Busca por familia, presentación o vía de administración.</flux:text>
                </div>
                <flux:button type="button" wire:click="refreshOptions('familias')" icon="arrow-path" size="sm">Actualizar</flux:button>
            </div>
            <flux:input wire:model.live.debounce.350ms="familySearch" label="Buscar familia" icon="magnifying-glass" placeholder="Familia, presentación o vía de administración" maxlength="255" clearable />
            <div wire:loading wire:target="retryFamilyPage,refreshOptions,familySearch,previousFamilyPage,nextFamilyPage" class="text-sm text-slate-500" role="status">Cargando familias…</div>
            @if ($familyLoadError)
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300" role="alert">
                    <p>{{ $familyLoadError }}</p>
                    <flux:button type="button" wire:click="retryFamilyPage" size="sm" class="mt-3">Reintentar</flux:button>
                </div>
            @elseif (! $familyLoaded)
                <p wire:loading.remove wire:target="retryFamilyPage,refreshOptions,familySearch,previousFamilyPage,nextFamilyPage" class="text-sm text-slate-500" role="status">Cargando familias…</p>
            @else
            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                <div class="farma-table-scroll max-h-104 overflow-x-hidden overflow-y-auto" tabindex="0">
                    <table class="w-full table-fixed divide-y divide-slate-200 text-sm dark:divide-slate-700" data-test="family-picker-table">
                        <colgroup><col class="w-[32%]" /><col class="w-[24%]" /><col class="w-[22%]" /><col class="w-28" /></colgroup>
                        <thead class="sticky top-0 bg-white text-left text-[11px] uppercase leading-tight tracking-wide text-slate-500 dark:bg-slate-900">
                            <tr>
                                <th class="px-2 py-2.5 font-semibold">Familia</th>
                                <th class="px-2 py-2.5 font-semibold">Presentación</th>
                                <th class="px-2 py-2.5 font-semibold">Administración</th>
                                <th class="px-2 py-2.5 text-right font-semibold">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900">
                            @forelse ($familyPicker['items'] as $family)
                                <tr wire:key="family-option-{{ $family['id'] }}">
                                    <td class="whitespace-normal px-2 py-2.5 font-medium text-slate-800 [overflow-wrap:anywhere] dark:text-slate-100" title="{{ $family['nombre'] ?? $family['label'] }}">{{ $family['nombre'] ?? $family['label'] }}</td>
                                    <td class="whitespace-normal px-2 py-2.5 text-slate-600 [overflow-wrap:anywhere] dark:text-slate-300" title="{{ $family['presentacion'] ?? '—' }}">{{ $family['presentacion'] ?? '—' }}</td>
                                    <td class="whitespace-normal px-2 py-2.5 text-slate-600 [overflow-wrap:anywhere] dark:text-slate-300" title="{{ $family['administracion'] ?? '—' }}">{{ $family['administracion'] ?? '—' }}</td>
                                    <td class="px-2 py-2.5 text-right"><flux:button type="button" wire:click="selectFamily({{ $family['id'] }})" wire:loading.attr="disabled" wire:target="retryFamilyPage,refreshOptions,familySearch,previousFamilyPage,nextFamilyPage,selectFamily" variant="primary" size="sm">Seleccionar</flux:button></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">No hay familias que coincidan con el filtro.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-3 py-2.5 dark:border-slate-700">
                    <p class="text-xs text-slate-500">{{ $familyPicker['total'] }} resultados · Página {{ $familyPicker['page'] }} de {{ $familyPicker['lastPage'] }}</p>
                    <div class="flex gap-2">
                        <flux:button type="button" wire:click="previousFamilyPage" wire:loading.attr="disabled" wire:target="retryFamilyPage,refreshOptions,familySearch,previousFamilyPage,nextFamilyPage" icon="chevron-left" size="sm" :disabled="$familyPicker['page'] <= 1">Anterior</flux:button>
                        <flux:button type="button" wire:click="nextFamilyPage" wire:loading.attr="disabled" wire:target="retryFamilyPage,refreshOptions,familySearch,previousFamilyPage,nextFamilyPage" icon-trailing="chevron-right" size="sm" :disabled="$familyPicker['page'] >= $familyPicker['lastPage']">Siguiente</flux:button>
                    </div>
                </div>
            </div>
            @endif
            <div class="flex justify-end"><flux:button type="button" wire:click="closeFamilyPicker">Cerrar</flux:button></div>
        </div>
    </flux:modal>
    @endif

    @if ($showUnitPicker)
    <flux:modal wire:key="product-unit-picker-modal" wire:model.self="showUnitPicker" class="products-content-modal w-full bg-white! dark:bg-slate-900! md:w-208">
        <div class="space-y-5" data-test="unit-picker-modal">
            <div class="flex flex-wrap items-start justify-between gap-3 pr-6">
                <div>
                    <flux:heading size="lg">Seleccionar unidades</flux:heading>
                    <flux:text class="mt-2">Selecciona la conversión que define la unidad de compra y la unidad de venta.</flux:text>
                </div>
                <flux:button type="button" wire:click="refreshOptions('unidades')" icon="arrow-path" size="sm">Actualizar</flux:button>
            </div>
            <flux:input wire:model.live.debounce.350ms="unitSearch" icon="magnifying-glass" placeholder="Filtrar por unidad de compra o venta" clearable />
            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                <div class="farma-table-scroll max-h-104 overflow-auto" tabindex="0">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="sticky top-0 bg-white text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900">
                            <tr>
                                <th class="px-3 py-3 font-semibold">Unidad Compra</th>
                                <th class="px-3 py-3 font-semibold">Unidad Venta</th>
                                <th class="w-24 px-3 py-3 text-right font-semibold">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900">
                            @forelse ($unitPicker['items'] as $unitOption)
                                <tr wire:key="unit-option-{{ $unitOption['id'] }}">
                                    <td class="px-3 py-3 font-medium text-slate-800 dark:text-slate-100">{{ ($unitOption['abreviatura_c'] ?? '—').(isset($unitOption['cantidad_c']) ? ' × '.$unitOption['cantidad_c'] : '') }}</td>
                                    <td class="px-3 py-3 font-medium text-slate-800 dark:text-slate-100">{{ ($unitOption['abreviatura_v'] ?? '—').(isset($unitOption['cantidad_v']) ? ' × '.$unitOption['cantidad_v'] : '') }}</td>
                                    <td class="px-3 py-3 text-right"><flux:button type="button" wire:click="selectUnit({{ $unitOption['id'] }})" variant="primary" size="sm">Seleccionar</flux:button></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-8 text-center text-slate-500">No hay unidades que coincidan con el filtro.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-slate-200 px-3 py-2.5 dark:border-slate-700">
                    <p class="text-xs text-slate-500">{{ $unitPicker['total'] }} resultados · Página {{ $unitPicker['page'] }} de {{ $unitPicker['lastPage'] }}</p>
                    <div class="flex gap-2">
                        <flux:button type="button" wire:click="previousUnitPage" icon="chevron-left" size="sm" :disabled="$unitPicker['page'] <= 1">Anterior</flux:button>
                        <flux:button type="button" wire:click="nextUnitPage" icon-trailing="chevron-right" size="sm" :disabled="$unitPicker['page'] >= $unitPicker['lastPage']">Siguiente</flux:button>
                    </div>
                </div>
            </div>
            <div class="flex justify-end"><flux:button type="button" wire:click="closeUnitPicker">Cerrar</flux:button></div>
        </div>
    </flux:modal>
    @endif

    @if ($showActivePicker)
    <flux:modal wire:key="product-active-picker-modal" wire:model.self="showActivePicker" class="products-content-modal w-full bg-white! dark:bg-slate-900! md:w-184">
        <div wire:init="retryActivePage" class="space-y-5" data-test="active-picker-modal">
            <div class="flex flex-wrap items-start justify-between gap-3 pr-6">
                <div>
                    <flux:heading size="lg">Agregar principio activo</flux:heading>
                    <flux:text class="mt-2">Filtra por nombre y agrega los principios activos requeridos.</flux:text>
                </div>
                <flux:button type="button" wire:click="refreshOptions('principios_activos')" icon="arrow-path" size="sm">Actualizar</flux:button>
            </div>
            <flux:input wire:model.live.debounce.350ms="activeSearch" label="Buscar principio activo" icon="magnifying-glass" placeholder="Nombre o concentración" maxlength="255" clearable />
            <div wire:loading wire:target="retryActivePage,refreshOptions,activeSearch,previousActivePage,nextActivePage" class="text-sm text-slate-500" role="status">Cargando principios activos…</div>
            @if ($activeLoadError)
                <div role="alert" class="text-sm text-red-600">
                    <p>{{ $activeLoadError }}</p>
                    <flux:button type="button" wire:click="retryActivePage" size="sm">Reintentar</flux:button>
                </div>
            @elseif (! $activeLoaded)
                <p class="text-sm text-slate-500" role="status">Cargando principios activos…</p>
            @else
            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                <div class="farma-table-scroll max-h-104 overflow-auto" tabindex="0">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="sticky top-0 bg-white text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900">
                            <tr>
                                <th class="px-3 py-3 font-semibold">Principio activo</th>
                                <th class="px-3 py-3 font-semibold">Concentración</th>
                                <th class="w-24 px-3 py-3 text-right font-semibold">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900">
                            @forelse ($activePicker['items'] as $activeOption)
                                @php($alreadySelected = in_array((int) $activeOption['id'], array_map('intval', $principiosActivos), true))
                                <tr wire:key="active-option-{{ $activeOption['id'] }}">
                                    <td class="px-3 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $activeOption['nombre'] ?? $activeOption['label'] }}</td>
                                    <td class="px-3 py-3 text-slate-600 dark:text-slate-300">{{ data_get($activeOption, 'concentracion_detalle.valor', '—') }}</td>
                                    <td class="px-3 py-3 text-right">
                                        <flux:button type="button" wire:click="addActive({{ $activeOption['id'] }})" :variant="$alreadySelected ? 'ghost' : 'primary'" size="sm" :disabled="$alreadySelected">
                                            {{ $alreadySelected ? 'Agregado' : 'Agregar' }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-8 text-center text-slate-500">No hay principios activos que coincidan con el filtro.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-slate-200 px-3 py-2.5 dark:border-slate-700">
                    <p class="text-xs text-slate-500">{{ $activePicker['total'] }} resultados · Página {{ $activePicker['page'] }} de {{ $activePicker['lastPage'] }}</p>
                    <div class="flex gap-2">
                        <flux:button type="button" wire:click="previousActivePage" icon="chevron-left" size="sm" :disabled="$activePicker['page'] <= 1">Anterior</flux:button>
                        <flux:button type="button" wire:click="nextActivePage" icon-trailing="chevron-right" size="sm" :disabled="$activePicker['page'] >= $activePicker['lastPage']">Siguiente</flux:button>
                    </div>
                </div>
            </div>
            @endif
            <div class="flex justify-end"><flux:button type="button" wire:click="closeActivePicker">Listo</flux:button></div>
        </div>
    </flux:modal>
    @endif

    @if ($showChildModal)
    <flux:modal wire:key="product-child-modal" wire:model.self="showChildModal" class="products-content-modal bg-white! dark:bg-slate-900! md:w-120">
        <form wire:submit="createChild" class="space-y-5" data-test="product-child-form">
            <div>
                <flux:heading size="lg">Convertir a hijo</flux:heading>
                <flux:text class="mt-2">Se creará un producto hijo de {{ $childParentName }} con los mismos datos del padre.</flux:text>
            </div>
            <flux:input :value="$childCode" label="Código del hijo" readonly />
            <flux:input wire:model="childDescription" label="Descripción del hijo" maxlength="150" required />
            <flux:text>Unidades: cantidad de compra 1 y cantidad de venta 1.</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeChild">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="createChild">Crear hijo</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif

    @if ($showParentPicker)
    <flux:modal wire:key="product-parent-picker" wire:model.self="showParentPicker" class="products-content-modal w-full md:w-184">
        <div class="space-y-5" data-test="product-parent-picker">
            <flux:heading size="lg">Seleccionar producto padre</flux:heading>
            <flux:input wire:model.live.debounce.500ms="parentSearch" label="Buscar producto" placeholder="Código, código de barras o descripción" maxlength="150" icon="magnifying-glass" />
            @if ($parentLoadError) <flux:text>{{ $parentLoadError }}</flux:text> @endif
            <div class="farma-table-scroll max-h-96 overflow-auto" tabindex="0">
                <table class="w-full text-left text-sm">
                    <thead><tr><th class="p-2">Código</th><th class="p-2">Descripción</th><th class="p-2">Acción</th></tr></thead>
                    <tbody>
                        @forelse ($parentOptions as $option)
                            <tr wire:key="parent-option-{{ $option['id'] }}">
                                <td class="p-2">{{ $option['codigo'] ?? '—' }}</td>
                                <td class="p-2">{{ $option['descripcion'] ?? '—' }}</td>
                                <td class="p-2"><flux:button type="button" wire:click="selectParent('{{ $option['id'] }}')" size="sm">Seleccionar</flux:button></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-4">No hay productos para esta búsqueda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex justify-between gap-2">
                <flux:button type="button" wire:click="parentPage({{ $parentPage - 1 }})" :disabled="$parentPage <= 1">Anterior</flux:button>
                <flux:text>{{ $parentPage }} / {{ $parentLastPage }}</flux:text>
                <flux:button type="button" wire:click="parentPage({{ $parentPage + 1 }})" :disabled="$parentPage >= $parentLastPage">Siguiente</flux:button>
            </div>
            <flux:button type="button" wire:click="closeParentPicker">Cerrar</flux:button>
        </div>
    </flux:modal>
    @endif

    @if ($showDeleteModal)
    <flux:modal wire:key="product-delete-modal" wire:model.self="showDeleteModal" class="products-content-modal bg-white! dark:bg-slate-900! md:w-120">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Eliminar producto</flux:heading>
                <flux:text class="mt-2">
                    ¿Deseas eliminar <strong>{{ $deletingName }}</strong>? No será posible si tiene movimientos relacionados.
                </flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelDelete">Cancelar</flux:button>
                <flux:button wire:click="delete" variant="danger" icon="trash">Eliminar</flux:button>
            </div>
        </div>
    </flux:modal>
    @endif
</div>
