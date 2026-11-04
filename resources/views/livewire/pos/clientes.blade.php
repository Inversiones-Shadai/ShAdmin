<div class="space-y-6" data-test="clientepos-screen">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Clientes POS</flux:heading>
            <flux:text class="mt-1">Datos de contacto y facturación de los clientes.</flux:text>
        </div>
        @if ($access['create'])
            <flux:button wire:click="openCreate" variant="primary" icon="plus" data-test="create-clientepos">Nuevo cliente</flux:button>
        @endif
    </div>

    @if ($loadError)
        <flux:callout variant="danger" heading="No fue posible cargar los clientes">{{ $loadError }}</flux:callout>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <flux:text>{{ $total }} clientes registrados</flux:text>
            <flux:input wire:model.live.debounce.500ms="search" icon="magnifying-glass" placeholder="Buscar nombre, RTN, identidad, teléfono o dirección" maxlength="180" class="sm:max-w-md" aria-label="Buscar clientes" />
        </div>
        @if ($clients === [])
            <div class="p-10 text-center text-slate-500">{{ $search !== '' ? 'No hay clientes que coincidan con la búsqueda.' : 'No hay clientes registrados.' }}</div>
        @else
            <div class="overflow-x-auto p-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Nombre</flux:table.column>
                        <flux:table.column>RTN / identidad</flux:table.column>
                        <flux:table.column>Tipo de documento</flux:table.column>
                        <flux:table.column>Nacimiento</flux:table.column>
                        <flux:table.column>Teléfono</flux:table.column>
                        <flux:table.column>Dirección</flux:table.column>
                        <flux:table.column align="end">Acciones</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($clients as $client)
                            <flux:table.row :key="$client['id']">
                                <flux:table.cell variant="strong">{{ $client['nombre'] }}</flux:table.cell>
                                <flux:table.cell>{{ $client['rtn'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell>{{ ($client['tipo_documento'] ?? null) === 'IDENTIDAD' ? 'Identidad' : (($client['tipo_documento'] ?? null) === 'RTN' ? 'RTN' : 'Sin clasificar') }}</flux:table.cell>
                                <flux:table.cell>{{ ($client['fecha_nacimiento'] ?? null) ? \Carbon\Carbon::parse($client['fecha_nacimiento'])->format('d/m/Y') : '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $client['telefono'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="min-w-48 whitespace-normal">{{ $client['direccion'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-1">
                                        @if ($access['edit'])
                                            <flux:button wire:click="openEdit({{ $client['id'] }})" icon="pencil-square" variant="ghost" size="sm" square tooltip="Editar cliente" data-test="edit-clientepos" />
                                        @endif
                                        @if ($access['delete'])
                                            <flux:button wire:click="confirmDelete({{ $client['id'] }})" icon="trash" variant="ghost" size="sm" square tooltip="Eliminar cliente" data-test="delete-clientepos" />
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
            <div class="flex items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800">
                <flux:text>Página {{ $page }} de {{ $lastPage }}</flux:text>
                <div class="flex gap-2">
                    <flux:button wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1" wire:loading.attr="disabled">Anterior</flux:button>
                    <flux:button wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage" wire:loading.attr="disabled">Siguiente</flux:button>
                </div>
            </div>
        @endif
    </section>

    <flux:modal wire:model.self="showFormModal" class="w-full md:w-[38rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId === null ? 'Nuevo cliente' : 'Editar cliente' }}</flux:heading>
            <flux:input wire:model="nombre" label="Nombre completo o razón social" maxlength="180" required />
            <flux:select wire:model.live="tipo_documento" label="Tipo de documento" placeholder="Selecciona Identidad o RTN">
                <flux:select.option value="IDENTIDAD">Identidad · 13 dígitos</flux:select.option>
                <flux:select.option value="RTN">RTN · 14 dígitos</flux:select.option>
            </flux:select>
            @if ($legacyDocument && $tipo_documento === '')
                <flux:text>Este registro anterior conserva su documento sin clasificar. Selecciona el tipo para completar sus datos.</flux:text>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="rtn" :label="$tipo_documento === 'IDENTIDAD' ? 'Número de identidad' : 'RTN / documento'" maxlength="20" inputmode="numeric" />
                <flux:input wire:model="telefono" label="Teléfono" type="tel" maxlength="20" />
            </div>
            @if ($tipo_documento !== '')
                <flux:input wire:model="fecha_nacimiento" type="date" :label="$tipo_documento === 'IDENTIDAD' ? 'Fecha de nacimiento' : 'Fecha de nacimiento (solo si corresponde a una persona)'" :max="now('America/Tegucigalpa')->toDateString()" :required="$tipo_documento === 'IDENTIDAD'" />
                <flux:text>Los descuentos por edad en POS requieren un cliente registrado con fecha de nacimiento.</flux:text>
            @endif
            <flux:textarea wire:model="direccion" label="Dirección" maxlength="255" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeForm">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-96">
        <div class="space-y-5">
            <flux:heading size="lg">Eliminar cliente</flux:heading>
            <flux:text>¿Deseas eliminar a <strong>{{ $deletingName }}</strong>?</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelDelete">Cancelar</flux:button>
                <flux:button wire:click="delete" variant="danger" wire:loading.attr="disabled" wire:target="delete">Eliminar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
