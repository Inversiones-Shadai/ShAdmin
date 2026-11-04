<div class="space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">Dispositivos</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Administra los equipos, su stock y el emparejamiento con HQ.</p>
        </div>

        @if ($access['createDevices'])
            <flux:button wire:click="openCreate" variant="primary" icon="plus" data-test="create-device-button">
                Nuevo dispositivo
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
                <h2 class="font-semibold text-slate-950 dark:text-white">Listado de dispositivos</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ count($visibleDevices) }} en esta página · {{ $total }} en total
                </p>
            </div>

            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Buscar dispositivo..."
                class="w-full sm:max-w-xs"
            />
        </div>

        @if ($visibleDevices === [])
            <div class="px-6 py-14 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    <flux:icon.device class="size-6" />
                </div>
                <h3 class="mt-4 font-medium text-slate-950 dark:text-white">No hay dispositivos para mostrar</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $search !== '' ? 'Prueba con otro término de búsqueda.' : 'Registra el primer dispositivo para comenzar.' }}
                </p>
            </div>
        @else
            <div data-test="devices-table-container" class="overflow-x-auto px-4 pb-4 pt-2 sm:px-6">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="py-3.5">ID</flux:table.column>
                        <flux:table.column class="py-3.5">Dispositivo</flux:table.column>
                        <flux:table.column class="py-3.5">Stock</flux:table.column>
                        <flux:table.column class="py-3.5">UUID de emparejamiento HQ</flux:table.column>
                        <flux:table.column class="py-3.5">Estado</flux:table.column>
                        <flux:table.column class="py-3.5">Actualizado</flux:table.column>
                        <flux:table.column class="py-3.5" align="end">Acciones</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($visibleDevices as $device)
                            <flux:table.row :key="$device['id']">
                                <flux:table.cell class="py-4 font-mono text-xs">{{ $device['id'] }}</flux:table.cell>
                                <flux:table.cell class="min-w-52 py-4" variant="strong">
                                    {{ $device['displayname'] ?: '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="min-w-48 py-4">
                                    {{ $device['stock_label'] ?: 'Sin stock' }}
                                </flux:table.cell>
                                <flux:table.cell class="min-w-52 max-w-64 py-4">
                                    @if ($device['hq_pairing_uuid'] !== '')
                                        <code class="select-all whitespace-normal break-all font-mono text-xs">{{ $device['hq_pairing_uuid'] }}</code>
                                    @else
                                        <span class="text-sm text-slate-400">Sin emparejar</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => strtoupper(trim($device['state_label'])) === 'ACTIVO',
                                        'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300' => strtoupper(trim($device['state_label'])) === 'INACTIVO',
                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! in_array(strtoupper(trim($device['state_label'])), ['ACTIVO', 'INACTIVO'], true),
                                    ])>
                                        {{ $device['state_label'] ?: 'Sin estado' }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="min-w-44 py-4 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $device['updated_at'] ?: '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="py-4" align="end">
                                    <div class="flex justify-end gap-1">
                                        @if ($access['editDevices'])
                                            <flux:button
                                                wire:click="openEdit({{ $device['id'] }})"
                                                icon="pencil-square"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Editar dispositivo"
                                                data-test="edit-device-button"
                                            />
                                        @endif
                                        @if ($access['deleteDevices'])
                                            <flux:button
                                                wire:click="confirmDelete({{ $device['id'] }})"
                                                icon="trash"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Eliminar dispositivo"
                                                data-test="delete-device-button"
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
            <nav class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-700" aria-label="Paginación de dispositivos">
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

    <flux:modal wire:model.self="showFormModal" class="w-full md:w-[44rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId === null ? 'Crear dispositivo' : 'Editar dispositivo' }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ $editingId === null
                        ? 'Registra la identidad del equipo y asígnalo a un stock.'
                        : 'Actualiza el estado y el UUID de emparejamiento con HQ.' }}
                </flux:text>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                @if ($editingId === null)
                    <flux:input
                        wire:model="displayname"
                        label="Nombre visible"
                        maxlength="60"
                        class="sm:col-span-2"
                    />
                    <flux:input
                        wire:model="ip"
                        label="IP"
                        maxlength="100"
                        placeholder="192.168.1.10"
                    />
                    <flux:input
                        wire:model="ip2"
                        label="IP secundaria"
                        maxlength="100"
                        placeholder="192.168.1.11"
                    />
                    <flux:input
                        wire:model="deviceName"
                        label="Nombre del equipo"
                        maxlength="100"
                        placeholder="DESKTOP-EJEMPLO"
                        class="sm:col-span-2"
                    />
                    <x-remote-select source="device-stocks" cache="stocks" wire:model="stock" label="Stock" placeholder="Selecciona un stock" />
                @else
                    <flux:input
                        wire:model="displayname"
                        label="Dispositivo"
                        disabled
                    />
                @endif

                <x-remote-select source="device-states" cache="states" wire:model="estado" label="Estado" placeholder="Selecciona un estado" />
            </div>

            <section class="space-y-3 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <flux:heading>Emparejamiento con HQ</flux:heading>
                <flux:input
                    wire:model="hqPairingUuid"
                    label="UUID de emparejamiento HQ"
                    maxlength="36"
                    placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                    autocomplete="off"
                    data-test="hq-pairing-uuid-input"
                />
                <flux:text>En HQ, pulsa Generar UUID y copia el ID del dispositivo. Pégalo aquí, guarda los cambios y vuelve a HQ para solicitar el código de autorización. Cada UUID se puede vincular a un solo dispositivo.</flux:text>
            </section>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeForm">
                    Cancelar
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingId === null ? 'Crear dispositivo' : 'Guardar cambios' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Eliminar dispositivo</flux:heading>
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
