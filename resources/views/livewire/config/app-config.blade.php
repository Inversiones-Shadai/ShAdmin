<div class="space-y-6" data-test="app-config-screen">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Configuración de aplicación</flux:heading>
            <flux:text class="mt-2">Administra la versión y los correos de la aplicación.</flux:text>
        </div>
        @if ($access->allows('appconfig.create'))
            <flux:button wire:click="openCreate" variant="primary" icon="plus">Nueva configuración</flux:button>
        @endif
    </header>

    @if ($loadError)
        <flux:callout variant="danger" heading="No fue posible cargar la configuración">
            {{ $loadError }}
            <flux:button wire:click="loadPage({{ $page }})" size="sm" class="mt-3">Reintentar</flux:button>
        </flux:callout>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 p-4 dark:border-slate-800">
            <flux:heading>Configuraciones registradas</flux:heading>
            <flux:text>{{ $total }} en total</flux:text>
        </div>
        <div class="overflow-x-auto" tabindex="0" aria-label="Configuraciones de aplicación">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500 dark:border-slate-800">
                        <th scope="col" class="p-4">ID</th>
                        <th scope="col" class="p-4">Identificador de versión</th>
                        <th scope="col" class="p-4">Remitente de alertas</th>
                        <th scope="col" class="p-4">Administrador</th>
                        <th scope="col" class="p-4">Copias</th>
                        @if ($access->allows('appconfig.update'))<th scope="col" class="p-4 text-right">Acciones</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($records as $record)
                        <tr wire:key="app-config-{{ $record['id'] }}">
                            <td class="p-4 font-semibold">#{{ $record['id'] }}</td>
                            <td class="p-4"><p class="max-w-64 break-all font-mono text-xs">{{ $record['appv'] ?: 'Sin versión' }}</p></td>
                            <td class="p-4">{{ $record['mail_alert'] }}</td>
                            <td class="p-4">{{ $record['mail_cc1'] }}</td>
                            <td class="p-4">
                                <p>{{ $record['mail_cc2'] ?: 'Sin copia 2' }}</p>
                                <p class="mt-1 text-slate-500">{{ $record['mail_cc3'] ?: 'Sin copia 3' }}</p>
                            </td>
                            @if ($access->allows('appconfig.update'))
                                <td class="p-4 text-right"><flux:button wire:click="openEdit({{ $record['id'] }})" size="sm" icon="pencil-square" aria-label="Editar configuración {{ $record['id'] }}">Editar</flux:button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-10 text-center text-slate-500">{{ $loadError ? 'Consulta no disponible.' : 'No hay configuraciones registradas.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between gap-4 border-t border-slate-200 p-4 dark:border-slate-800">
            <flux:text>Página {{ $page }} de {{ $lastPage }}</flux:text>
            <div class="flex gap-2">
                <flux:button wire:click="loadPage({{ $page - 1 }})" size="sm" :disabled="$page <= 1" wire:loading.attr="disabled">Anterior</flux:button>
                <flux:button wire:click="loadPage({{ $page + 1 }})" size="sm" :disabled="$page >= $lastPage" wire:loading.attr="disabled">Siguiente</flux:button>
            </div>
        </div>
    </section>

    @if ($access->allowsAny(['appconfig.create', 'appconfig.update']))
    <flux:modal wire:model.self="showForm" class="w-full md:max-w-2xl">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId === null ? 'Nueva configuración' : 'Editar configuración #'.$editingId }}</flux:heading>
            @if ($saveError)<flux:callout variant="danger">{{ $saveError }}</flux:callout>@endif
            <flux:textarea wire:model="appv" label="Identificador de versión (appv)" rows="2" maxlength="65535" description="Valor exacto que envían los clientes en App-Version. Si utilizan un hash, conserva ese identificador." />
            <flux:input wire:model="mail_alert" label="Remitente de alertas" type="email" maxlength="255" required description="Dirección utilizada para enviar los avisos de asistencia." />
            <flux:input wire:model="mail_cc1" label="Correo administrador" type="email" maxlength="255" required description="Recibe avisos de asistencia y solicitudes de autorización de sucursales y licencias." />
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="mail_cc2" label="Correo de copia 2" type="email" maxlength="255" description="Opcional." />
                <flux:input wire:model="mail_cc3" label="Correo de copia 3" type="email" maxlength="255" description="Opcional." />
            </div>
            <div class="flex justify-end gap-3">
                <flux:button type="button" wire:click="closeForm">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Guardar configuración</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
