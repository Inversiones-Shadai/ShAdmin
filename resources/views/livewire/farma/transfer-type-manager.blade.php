<div class="farma-screen" data-test="transfer-type-catalog">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Tipos de transferencia</flux:heading>
            <flux:text class="mt-1">Administra los tipos de transferencia que se sincronizan con HQ.</flux:text>
        </div>
        @if ($access['create'])
            <flux:button wire:click="openCreate" variant="primary" icon="plus">Nuevo tipo</flux:button>
        @endif
    </div>

    <div class="farma-table-panel overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" placeholder="Buscar descripción…" aria-label="Buscar tipos de transferencia" maxlength="100" class="max-w-md" />
            <span class="text-sm text-slate-500">{{ number_format($total) }} registros</span>
        </div>
        @if ($loadError)
            <div class="p-6 text-center text-red-600" role="alert">{{ $loadError }}</div>
        @elseif ($records === [])
            <div class="p-10 text-center text-slate-500">No hay registros para mostrar.</div>
        @else
            <div class="farma-table-scroll overflow-x-auto" tabindex="0">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase text-slate-500">
                            <th scope="col" class="w-24 px-4 py-3">ID</th>
                            <th scope="col" class="px-4 py-3">Descripción</th>
                            <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($records as $record)
                            <tr wire:key="transfer-type-catalog-{{ $record['id'] }}">
                                <td class="px-4 py-3">{{ $record['id'] }}</td>
                                <td class="px-4 py-3 font-medium">{{ $record['descripcion'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        @if ($access['edit'])
                                            <flux:button wire:click="openEdit({{ $record['id'] }})" size="sm" variant="ghost" icon="pencil-square" aria-label="Editar tipo" />
                                        @endif
                                        @if ($access['delete'])
                                            <flux:button wire:click="confirmDelete({{ $record['id'] }})" size="sm" variant="ghost" icon="trash" aria-label="Eliminar tipo" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800">
                <span class="text-sm text-slate-500">Página {{ $page }} de {{ $lastPage }}</span>
                <div class="flex gap-2">
                    <flux:button wire:click="previousPage" size="sm" :disabled="$page <= 1">Anterior</flux:button>
                    <flux:button wire:click="nextPage" size="sm" :disabled="$page >= $lastPage">Siguiente</flux:button>
                </div>
            </div>
        @endif
    </div>

    <flux:modal wire:model.self="showFormModal" class="md:w-120">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Editar tipo' : 'Nuevo tipo' }}</flux:heading>
            <flux:input wire:model="descripcion" label="Descripción" maxlength="255" />
            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeForm">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-120">
        <div class="space-y-5">
            <flux:heading size="lg">Eliminar tipo</flux:heading>
            <flux:text>No se podrá eliminar si ya está relacionado con una transferencia.</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelDelete">Cancelar</flux:button>
                <flux:button wire:click="delete" variant="danger">Eliminar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
