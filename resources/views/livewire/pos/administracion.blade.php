<div class="space-y-6" data-test="{{ $resource }}pos-screen">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <flux:heading size="xl">{{ $resource === 'usuarios' ? 'Usuarios POS' : 'Roles POS' }}</flux:heading>
            <flux:text class="mt-1">{{ $resource === 'usuarios' ? 'Cuentas locales de POS disponibles en todos los stocks.' : 'El rol define el nivel del usuario. Los permisos se asignan desde Usuarios POS.' }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($access['create'])
                <flux:button wire:click="openCreate" variant="primary" icon="plus" data-test="create-{{ $resource }}pos">{{ $resource === 'usuarios' ? 'Nuevo usuario POS' : 'Nuevo rol POS' }}</flux:button>
            @endif
        </div>
    </div>
    <flux:callout icon="arrow-path" heading="Actualización mediante HQ">

    </flux:callout>
    @if (!$showFormModal && !$showDeactivateModal && !$showPermissionsModal)
        @error('form')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
    @endif
    @if ($loadError)
        <flux:callout variant="danger" heading="No fue posible cargar los registros">{{ $loadError }}</flux:callout>
        <flux:button wire:click="goToPage({{ $page }})">Reintentar</flux:button>
    @endif
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 p-4 dark:border-slate-800"><flux:text>{{ $total }} {{ $resource }} registrados</flux:text></div>
        @if ($records === [])
            <div class="p-10 text-center text-slate-500">No hay registros para mostrar.</div>
        @else
            <div class="overflow-x-auto p-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Nombre</flux:table.column>
                        @if ($resource === 'usuarios')
                            <flux:table.column>Vendedor</flux:table.column>
                            <flux:table.column>Rol</flux:table.column>
                        @else
                            <flux:table.column>Nivel</flux:table.column>
                        @endif
                        <flux:table.column>Estado</flux:table.column>
                        <flux:table.column align="end">Acciones</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($records as $record)
                            <flux:table.row :key="$record['id']">
                                <flux:table.cell variant="strong">{{ $record['nombre'] }}</flux:table.cell>
                                @if ($resource === 'usuarios')
                                    <flux:table.cell>{{ $record['vendedor'] ?? '—' }}</flux:table.cell>
                                    <flux:table.cell>{{ $record['rol_nombre'] ?? 'Rol #'.$record['rol_id'] }}</flux:table.cell>
                                @else
                                    <flux:table.cell>{{ $record['nivel'] ?? 0 }}</flux:table.cell>
                                @endif
                                <flux:table.cell><flux:badge :color="$record['activo'] ? 'green' : 'zinc'">{{ $record['activo'] ? 'Activo' : 'Inactivo' }}</flux:badge></flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-1">
                                        @if ($access['permissions'])
                                            <flux:button wire:click="openPermissions({{ $record['id'] }})" icon="shield-check" variant="ghost" size="sm" tooltip="Permisos" data-test="permissions-usuariospos">Permisos</flux:button>
                                        @endif
                                        @if ($access['edit'])
                                            <flux:button wire:click="openEdit({{ $record['id'] }})" icon="pencil-square" variant="ghost" size="sm" square tooltip="Editar" data-test="edit-{{ $resource }}pos" />
                                        @endif
                                        @if ($access['deactivate'] && $record['activo'])
                                            <flux:button wire:click="confirmDeactivate({{ $record['id'] }})" icon="no-symbol" variant="ghost" size="sm" square tooltip="Desactivar" data-test="deactivate-{{ $resource }}pos" />
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
    <flux:modal wire:model.self="showFormModal" class="w-full md:w-160">
        <form class="space-y-5" x-data="{ password: '', confirmation: '', saving: false }"
              x-on:pos-credentials-clear.window="password = ''; confirmation = ''"
              x-effect="if (!$wire.showFormModal) { password = ''; confirmation = '' }"
              x-on:submit.prevent="if (saving) return; saving = true; const operation = $wire.save(password, confirmation); password = ''; confirmation = ''; operation.finally(() => saving = false)">
            <flux:heading size="lg">{{ $editingId === null ? 'Crear' : 'Editar' }} {{ $resource === 'usuarios' ? 'usuario POS' : 'rol POS' }}</flux:heading>
            @error('form')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
            <flux:input wire:model="nombre" label="Nombre" :maxlength="$resource === 'usuarios' ? 200 : 80" required />
            @if ($resource === 'usuarios')
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-remote-select source="pos-roles" cache="roles" wire:model="rolId" label="Rol POS" placeholder="Selecciona un rol" />
                </div>
                <flux:input wire:model="vendedor" label="Vendedor" description="Nombre que aparecerá en las facturas de esta cuenta POS." maxlength="200" required />
                <flux:input wire:model="usuario" label="{{ $editingId === null ? 'Usuario de acceso POS' : 'Nuevo usuario de acceso (opcional)' }}" autocomplete="off" maxlength="200" :required="$editingId === null" />
                @if ($editingId !== null)<flux:text>Deja el usuario y la contraseña vacíos para conservar las credenciales actuales.</flux:text>@endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input x-model="password" type="password" label="{{ $editingId === null ? 'Contraseña' : 'Nueva contraseña (opcional)' }}" autocomplete="new-password" minlength="6" maxlength="1024" :required="$editingId === null" />
                    <flux:input x-model="confirmation" type="password" label="Confirmar contraseña" autocomplete="new-password" maxlength="1024" :required="$editingId === null" />
                </div>
                @error('password')<flux:text class="text-red-600">{{ $message }}</flux:text>@enderror
                <flux:text>Mínimo 6 caracteres. El acceso se valida localmente en cada POS.</flux:text>
            @else
                <flux:input wire:model="nivel" type="number" label="Nivel del usuario" min="0" max="1000" required />
                <flux:text>El nivel no concede permisos. Asígnalos individualmente desde Usuarios POS.</flux:text>
            @endif
            <flux:checkbox wire:model="activo" label="Activo" />
            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeForm" :disabled="$pendingId !== null">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" x-bind:disabled="saving" wire:loading.attr="disabled" wire:target="save">{{ $pendingId !== null ? 'Reintentar' : 'Guardar' }}</flux:button>
            </div>
        </form>
    </flux:modal>
    <flux:modal wire:model.self="showPermissionsModal" class="w-full max-w-[55rem] md:w-220">
        <div class="space-y-5">
            <flux:heading size="lg">Permisos POS — {{ $permissionUserName }}</flux:heading>
            @error('form')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
            @if ($access['edit'])
                <form wire:submit="savePermission" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-remote-select source="pos-permission-modules" cache="permissionModules" wire:model.live="permissionModule" label="Módulo" placeholder="Selecciona un módulo" :disabled="$pendingId !== null" />
                        <x-remote-select source="pos-permission-views" cache="permissionViews" :filters="['parent' => $permissionModule]" wire:model.live="permissionView" label="Vista" placeholder="Selecciona una vista" :disabled="$pendingId !== null || $permissionModule === ''" />
                        <x-remote-select source="pos-permission-actions" cache="permissionActions" :filters="['parent' => $permissionView]" wire:model="permissionAction" label="Acción / botón" placeholder="Selecciona una acción" :disabled="$pendingId !== null || $permissionView === ''" />
                        <x-remote-select source="pos-permission-times" cache="permissionTimes" wire:model="permissionTime" label="Tipo de tiempo" placeholder="Selecciona la duración" :disabled="$pendingId !== null" />
                    </div>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ $pendingId !== null ? 'Reintentar' : 'Asignar permiso' }}</flux:button>
                </form>
            @endif
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns><flux:table.column>Módulo</flux:table.column><flux:table.column>Vista</flux:table.column><flux:table.column>Acción</flux:table.column><flux:table.column>Creado</flux:table.column><flux:table.column /></flux:table.columns>
                    <flux:table.rows>
                        @forelse ($paginatedPermissions as $item)
                            <flux:table.row :key="$item['id']">
                                <flux:table.cell>{{ $item['modulo']['nombre'] }}</flux:table.cell>
                                <flux:table.cell>{{ $item['vista']['nombre'] }}</flux:table.cell>
                                <flux:table.cell>{{ $item['actionvista']['nombre'] }}</flux:table.cell>
                                <flux:table.cell>{{ $item['created_at'] }}</flux:table.cell>
                                <flux:table.cell>@if ($access['edit'])<flux:button wire:click="deletePermission({{ $item['id'] }})" wire:confirm="¿Eliminar este permiso POS?" icon="trash" variant="ghost" size="sm" tooltip="Eliminar permiso" wire:loading.attr="disabled" />@endif</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="5">Sin permisos POS asignados.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
            @if ($permissionLastPage > 1)
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:text>Página {{ $permissionPage }} de {{ $permissionLastPage }} · {{ count($userPermissions) }} permisos</flux:text>
                    <div class="flex gap-2">
                        <flux:button wire:click="goToPermissionsPage({{ $permissionPage - 1 }})" :disabled="$permissionPage <= 1" wire:loading.attr="disabled" wire:target="goToPermissionsPage">Anterior</flux:button>
                        <flux:button wire:click="goToPermissionsPage({{ $permissionPage + 1 }})" :disabled="$permissionPage >= $permissionLastPage" wire:loading.attr="disabled" wire:target="goToPermissionsPage">Siguiente</flux:button>
                    </div>
                </div>
            @endif
            <div class="flex justify-end"><flux:button wire:click="closePermissions" :disabled="$pendingId !== null">Cerrar</flux:button></div>
        </div>
    </flux:modal>
    <flux:modal wire:model.self="showDeactivateModal" class="md:w-96">
        <div class="space-y-5">
            <flux:heading size="lg">Desactivar {{ $resource === 'usuarios' ? 'usuario POS' : 'rol POS' }}</flux:heading>
            <flux:text>¿Deseas desactivar a <strong>{{ $deactivatingName }}</strong>? Se conserva su historial. HQ enviará el cambio al POS.</flux:text>
            @error('form')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelDeactivate" :disabled="$pendingId !== null">Cancelar</flux:button>
                <flux:button wire:click="deactivate" variant="danger" wire:loading.attr="disabled" wire:target="deactivate">Desactivar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
