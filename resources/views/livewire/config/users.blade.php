<div class="space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">Usuarios</h1>
        </div>

        @if ($access['createUsers'])
            <div class="flex items-center gap-2">
                <flux:button wire:click="openCreate" variant="primary" icon="plus" data-test="create-user-button">
                    Nuevo usuario
                </flux:button>
            </div>
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
                <h2 class="font-semibold text-slate-950 dark:text-white">Listado de usuarios</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ count($visibleUsers) }} de {{ count($users) }} registros
                </p>
            </div>

            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Buscar usuario..."
                class="w-full sm:max-w-xs"
            />
        </div>

        @if ($visibleUsers === [])
            <div class="px-6 py-14 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    <flux:icon.users class="size-6" />
                </div>
                <h3 class="mt-4 font-medium text-slate-950 dark:text-white">No hay usuarios para mostrar</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $search !== '' ? 'Prueba con otro término de búsqueda.' : 'Crea el primer usuario para comenzar.' }}
                </p>
            </div>
        @else
            <div data-test="users-table-container" class="px-4 pb-4 pt-2 sm:px-6">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>ID</flux:table.column>
                        <flux:table.column>Nombre</flux:table.column>
                        <flux:table.column>Usuario</flux:table.column>
                        <flux:table.column>Correo</flux:table.column>
                        <flux:table.column>Rol</flux:table.column>
                        <flux:table.column>Estado</flux:table.column>
                        <flux:table.column align="end">Acciones</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($visibleUsers as $user)
                            <flux:table.row :key="$user['id']">
                                <flux:table.cell class="font-mono text-xs">{{ $user['id'] }}</flux:table.cell>
                                <flux:table.cell variant="strong">{{ $user['nombre'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $user['name'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $user['email'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $user['role_label'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => strtoupper(trim($user['state_label'])) === 'ACTIVO',
                                        'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300' => strtoupper(trim($user['state_label'])) === 'INACTIVO',
                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! in_array(strtoupper(trim($user['state_label'])), ['ACTIVO', 'INACTIVO'], true),
                                    ])>
                                        {{ $user['state_label'] ?: 'Sin estado' }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-1">
                                        @if ($access['manageWorkHours'])
                                            <flux:button
                                                wire:click="openWorkHours({{ $user['id'] }})"
                                                icon="clock"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Asignar horario laboral"
                                                data-test="manage-work-hours-button"
                                            />
                                        @endif
                                        @if ($access['managePermissions'])
                                            <flux:button
                                                wire:click="openPermissions({{ $user['id'] }})"
                                                icon="shield-check"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Gestionar permisos"
                                                data-test="manage-permissions-button"
                                            />
                                        @endif
                                        @if ($access['editUsers'])
                                            <flux:button
                                                wire:click="openEdit({{ $user['id'] }})"
                                                icon="pencil-square"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Editar usuario"
                                                data-test="edit-user-button"
                                            />
                                        @endif
                                        @if ($access['deleteUsers'])
                                            <flux:button
                                                wire:click="confirmDelete({{ $user['id'] }})"
                                                icon="trash"
                                                variant="ghost"
                                                size="sm"
                                                square
                                                tooltip="Eliminar usuario"
                                                data-test="delete-user-button"
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
    </section>

    <flux:modal wire:model.self="showFormModal" class="w-full md:w-[42rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId === null ? 'Crear usuario' : 'Editar usuario' }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ $editingId === null
                        ? 'Completa los datos de la nueva cuenta.'
                        : 'Actualiza los datos de la cuenta seleccionada.' }}
                </flux:text>
            </div>

            @error('form')
                <flux:callout variant="danger" icon="exclamation-circle">
                    {{ $message }}
                </flux:callout>
            @enderror

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="nombre" label="Nombre completo" autocomplete="name" />
                <flux:input wire:model="name" label="Usuario" autocomplete="username" />
                <flux:input wire:model="email" type="email" label="Correo electrónico" autocomplete="email" class="sm:col-span-2" />

                <x-remote-select source="user-roles" cache="roles" wire:model="rol" label="Rol" placeholder="Selecciona un rol" />

                <x-remote-select source="user-states" cache="states" wire:model="estado" label="Estado" placeholder="Selecciona un estado" />

                <flux:input
                    wire:model="password"
                    type="password"
                    label="{{ $editingId === null ? 'Contraseña' : 'Nueva contraseña' }}"
                    description="{{ $editingId === null ? 'Mínimo 8 caracteres.' : 'Déjala vacía para conservar la actual.' }}"
                    autocomplete="new-password"
                    viewable
                />
                <flux:input
                    wire:model="passwordConfirmation"
                    type="password"
                    label="Confirmar contraseña"
                    autocomplete="new-password"
                    viewable
                />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeForm">
                    Cancelar
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingId === null ? 'Crear usuario' : 'Guardar cambios' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showWorkHoursModal" class="w-full md:w-[34rem]">
        <form wire:submit="saveWorkHours" class="space-y-6">
            <div>
                <flux:heading size="lg">Horario laboral de {{ $workHoursUserName }}</flux:heading>
                <flux:text class="mt-2">
                    Define la duración entre la entrada y la salida, además del tiempo permitido para almuerzo.
                </flux:text>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input
                    wire:model="workHours"
                    type="number"
                    min="1"
                    max="24"
                    step="1"
                    inputmode="numeric"
                    label="Horas de jornada"
                    description="De 1 a 24 horas."
                    icon="briefcase"
                    data-test="work-hours-input"
                />

                <flux:input
                    wire:model="lunchHours"
                    type="number"
                    min="1"
                    max="160"
                    step="1"
                    inputmode="numeric"
                    label="Minutos de almuerzo"
                    description="De 1 a 160 minutos."
                    icon="clock"
                    data-test="lunch-hours-input"
                />
            </div>

            <flux:callout icon="information-circle">
                Estos valores se usan para controlar los tiempos de entrada, salida e inicio y fin de almuerzo.
            </flux:callout>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeWorkHours">
                    Cancelar
                </flux:button>
                <flux:button
                    type="submit"
                    variant="primary"
                    icon="check"
                    wire:loading.attr="disabled"
                    wire:target="saveWorkHours"
                >
                    Guardar horario
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showPermissionsModal" class="w-[calc(100vw-2rem)] min-w-0 max-w-[76rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Permisos de {{ $permissionsUserName }}</flux:heading>
                <flux:text class="mt-2">
                    Asigna accesos por módulo, vista, acción y duración.
                </flux:text>
            </div>

            @if ($access['createPermissions'])
                <form wire:submit="savePermission" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-test="create-permission-form">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <x-remote-select source="permission-modules" cache="permissionModules" wire:model.live="permissionModule"
                            label="Módulo"
                            placeholder="Selecciona un módulo" />

                        <x-remote-select source="permission-views" cache="permissionViews" :filters="['parent' => $permissionModule]" wire:model.live="permissionView"
                            label="Vista"
                            placeholder="Selecciona una vista"
                            :disabled="$permissionModule === ''" />

                        <x-remote-select source="permission-actions" cache="permissionActions" :filters="['parent' => $permissionView]" wire:model="permissionAction"
                            label="Acción"
                            placeholder="Selecciona una acción"
                            :disabled="$permissionView === ''" />

                        <x-remote-select source="permission-times" cache="permissionTimeTypes" wire:model="permissionTimeType"
                            label="Tipo de tiempo"
                            placeholder="Selecciona una duración" />
                    </div>

                    <div class="mt-4 flex justify-end">
                        <flux:button type="submit" variant="primary" icon="plus">
                            Agregar permiso
                        </flux:button>
                    </div>
                </form>
            @endif

            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                    <h3 class="font-medium text-slate-950 dark:text-white">Permisos asignados</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $permissionTotal }} {{ $permissionTotal === 1 ? 'permiso' : 'permisos' }} en total
                    </p>
                </div>

                @if ($permissions === [])
                    <div class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                        Este usuario todavía no tiene permisos asignados.
                    </div>
                @else
                    <div data-test="permissions-table-container" class="overflow-x-auto px-4 pb-4 pt-2 sm:px-6">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column class="py-3.5">Módulo</flux:table.column>
                                <flux:table.column class="py-3.5">Vista</flux:table.column>
                                <flux:table.column class="py-3.5">Acción</flux:table.column>
                                <flux:table.column class="py-3.5">Duración</flux:table.column>
                                <flux:table.column class="py-3.5" align="end">Acciones</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($permissions as $permission)
                                    <flux:table.row :key="$permission['id']">
                                        <flux:table.cell class="py-4" variant="strong">{{ $permission['module_label'] ?: '—' }}</flux:table.cell>
                                        <flux:table.cell class="py-4">{{ $permission['view_label'] ?: '—' }}</flux:table.cell>
                                        <flux:table.cell class="py-4">{{ $permission['action_label'] ?: '—' }}</flux:table.cell>
                                        <flux:table.cell class="py-4">{{ $permission['time_label'] ?: '—' }}</flux:table.cell>
                                        <flux:table.cell class="py-4" align="end">
                                            @if ($access['deletePermissions'])
                                                <flux:button
                                                    wire:click="confirmPermissionDelete({{ $permission['id'] }})"
                                                    icon="trash"
                                                    variant="ghost"
                                                    size="sm"
                                                    square
                                                    tooltip="Eliminar permiso"
                                                    data-test="delete-permission-button"
                                                />
                                            @endif
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif

                @if ($permissionLastPage > 1)
                    <nav
                        class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-700"
                        aria-label="Paginación de permisos"
                    >
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Página {{ $permissionPage }} de {{ $permissionLastPage }}
                        </p>

                        <div class="flex items-center gap-2">
                            <flux:button
                                wire:click="previousPermissionPage"
                                wire:loading.attr="disabled"
                                icon="chevron-left"
                                size="sm"
                                :disabled="$permissionPage <= 1"
                            >
                                Anterior
                            </flux:button>
                            <flux:button
                                wire:click="nextPermissionPage"
                                wire:loading.attr="disabled"
                                icon-trailing="chevron-right"
                                size="sm"
                                :disabled="$permissionPage >= $permissionLastPage"
                            >
                                Siguiente
                            </flux:button>
                        </div>
                    </nav>
                @endif
            </div>

            <div class="flex justify-end">
                <flux:button wire:click="closePermissions">
                    Cerrar
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showPermissionDeleteModal" class="md:w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Eliminar permiso</flux:heading>
                <flux:text class="mt-2">
                    ¿Deseas eliminar el permiso <strong>{{ $deletingPermissionName }}</strong>?
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelPermissionDelete">
                    Cancelar
                </flux:button>
                <flux:button wire:click="deletePermission" variant="danger" icon="trash">
                    Eliminar permiso
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Eliminar usuario</flux:heading>
                <flux:text class="mt-2">
                    ¿Deseas eliminar a <strong>{{ $deletingName }}</strong>? Esta acción no se puede deshacer.
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
