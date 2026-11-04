<div class="space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Sorteos</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">Generación de tickets</h1>
        </div>

        <div class="flex flex-wrap gap-2">

            @if ($access['generateTickets'])
                <flux:button
                    wire:click="openGenerator"
                    wire:loading.attr="disabled"
                    variant="primary"
                    icon="ticket"
                    data-test="generate-ticket-button"
                >
                    Generar ticket
                </flux:button>
            @endif
        </div>
    </section>

    @if ($loadError)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="No fue posible completar la solicitud">
            {{ $loadError }}
        </flux:callout>
    @endif

    @if ($promotionError)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="Sorteo no disponible">
            {{ $promotionError }}
        </flux:callout>
    @elseif ($activePromotion)
        <section class="grid gap-4 rounded-xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm sm:grid-cols-[1fr_auto] sm:items-center dark:border-emerald-900 dark:bg-emerald-950/40">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Sorteo activo</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-950 dark:text-white">{{ $activePromotion['nombre'] }}</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    Finaliza: {{ $activePromotion['fecha_fin'] ?: 'Sin fecha definida' }}
                </p>
            </div>

            <div class="flex gap-6 text-sm">
                <div>
                    <p class="text-slate-500 dark:text-slate-400">Valor</p>
                    <p class="font-semibold text-slate-950 dark:text-white">L {{ number_format($activePromotion['valor'], 2) }}</p>
                </div>
            </div>
        </section>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 p-4 dark:border-slate-800">
            <h2 class="font-semibold text-slate-950 dark:text-white">Tickets del sorteo activo</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ count($tickets) }} en esta página · {{ $total }} en total
            </p>
        </div>

        @if ($tickets === [])
            <div class="px-6 py-14 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    <flux:icon.ticket class="size-6" />
                </div>
                <h3 class="mt-4 font-medium text-slate-950 dark:text-white">No hay tickets para mostrar</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Genera el primer ticket del sorteo activo.
                </p>
            </div>
        @else
            <div class="overflow-x-auto px-4 pb-4 pt-2 sm:px-6" data-test="tickets-table-container">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="py-3.5">Ticket</flux:table.column>
                        <flux:table.column class="py-3.5">Cliente</flux:table.column>
                        <flux:table.column class="py-3.5">Sorteo</flux:table.column>
                        <flux:table.column class="py-3.5">Punto</flux:table.column>
                        <flux:table.column class="py-3.5">Generado por</flux:table.column>
                        <flux:table.column class="py-3.5">Fecha</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($tickets as $ticket)
                            <flux:table.row :key="$ticket['id']">
                                <flux:table.cell class="py-4">
                                    <span class="font-mono font-semibold text-slate-950 dark:text-white">
                                        #{{ $ticket['number'] ?: $ticket['id'] }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="min-w-48 py-4">
                                    <div class="font-medium text-slate-950 dark:text-white">{{ $ticket['client'] ?: '—' }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $ticket['document'] ?: 'Sin identidad' }}</div>
                                </flux:table.cell>
                                <flux:table.cell class="min-w-40 py-4">{{ $ticket['promotion'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="min-w-28 py-4">{{ $ticket['stock'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="min-w-32 py-4">{{ $ticket['user'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="min-w-36 py-4 text-sm">{{ $ticket['created_at'] ?: '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif

        @if ($lastPage > 1)
            <nav class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-700" aria-label="Paginación de tickets">
                <p class="text-sm text-slate-500 dark:text-slate-400">Página {{ $page }} de {{ $lastPage }}</p>

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

    <flux:modal wire:model.self="showTicketModal" class="w-full md:w-[58rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Generar ticket</flux:heading>
                <flux:text class="mt-2">
                    Selecciona el cliente, confirma su teléfono y envía el ticket a la impresora predeterminada.
                </flux:text>
            </div>

            <section class="space-y-3">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-950 dark:text-white">Cliente</h3>
                    </div>

                    @if ($access['createClients'])
                        <flux:button wire:click="openClientForm" icon="user-plus" size="sm" data-test="create-ticket-client-button">
                            Nuevo cliente
                        </flux:button>
                    @endif
                </div>

                @if ($access['searchClients'])
                    <form wire:submit="searchClients" class="flex flex-col gap-2 sm:flex-row">
                        <flux:input
                            wire:model.live.debounce.350ms="clientSearch"
                            icon="magnifying-glass"
                            placeholder="Identidad, nombre o apellido..."
                            class="w-full"
                        />
                        <div class="flex gap-2">
                            <flux:button type="submit" variant="primary">Buscar</flux:button>
                            @if ($clientSearch !== '')
                                <flux:button type="button" wire:click="clearClientSearch">Limpiar</flux:button>
                            @endif
                        </div>
                    </form>
                @endif

                <div class="max-h-60 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-700">
                    @forelse ($clients as $client)
                        <button
                            type="button"
                            wire:key="ticket-client-{{ $client['id'] }}"
                            wire:click="selectClient({{ $client['id'] }})"
                            @class([
                                'flex w-full items-center justify-between gap-4 border-b border-slate-200 px-4 py-3 text-left transition last:border-b-0 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800',
                                'bg-blue-50 dark:bg-blue-950/40' => $selectedClientId === $client['id'],
                            ])
                        >
                            <span>
                                <span class="block font-medium text-slate-950 dark:text-white">{{ $client['name'] }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ $client['document'] }}</span>
                            </span>
                            @if ($selectedClientId === $client['id'])
                                <flux:icon.check-circle class="size-5 text-blue-600 dark:text-blue-400" />
                            @endif
                        </button>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                            No hay clientes cargados. Usa la búsqueda o crea uno nuevo.
                        </p>
                    @endforelse
                </div>

                @error('selectedClientId')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                <div class="flex items-center justify-between gap-3 text-sm" data-test="ticket-client-pagination">
                    <flux:button size="sm" wire:click="changeClientPage(-1)" :disabled="$clientPage <= 1" wire:loading.attr="disabled">Anterior</flux:button>
                    <span>{{ $clientPage }} / {{ $clientLastPage }}</span>
                    <flux:button size="sm" wire:click="changeClientPage(1)" :disabled="$clientPage >= $clientLastPage" wire:loading.attr="disabled">Siguiente</flux:button>
                </div>
            </section>

            @if ($selectedClientId)
                <section class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-semibold text-slate-950 dark:text-white">{{ $selectedClientName }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $selectedClientDocument }}</p>
                        </div>

                        @if ($phoneStatus === 'active')
                            <flux:badge color="green" icon="check-circle">Teléfono verificado</flux:badge>
                        @elseif ($phoneStatus === 'required')
                            <flux:badge color="amber" icon="exclamation-triangle">Actualización requerida</flux:badge>
                        @else
                            <flux:badge color="zinc">Teléfono sin verificar</flux:badge>
                        @endif
                    </div>

                    @if ($phoneStatus === 'required')
                        @if ($access['updatePhone'])
                            <form wire:submit="updatePhone" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end">
                                <flux:input
                                    wire:model="phone"
                                    label="Nuevo teléfono"
                                    inputmode="numeric"
                                    maxlength="8"
                                    placeholder="99999999"
                                    class="w-full"
                                />
                                <flux:button type="submit" variant="primary" icon="phone">Actualizar y verificar</flux:button>
                            </form>
                        @else
                            <flux:callout class="mt-4" variant="warning" icon="lock-closed">
                                El teléfono debe actualizarse, pero tu usuario no tiene permiso para realizar esta acción.
                            </flux:callout>
                        @endif
                    @endif

                    @error('phone')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </section>
            @endif

            @if ($access['copies'])
                <flux:input
                    wire:model="copies"
                    type="number"
                    min="1"
                    max="10"
                    label="Copias"
                    description="Se enviarán juntas a la impresora predeterminada."
                    class="max-w-40"
                />
            @endif

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeGenerator">Cancelar</flux:button>
                <flux:button
                    type="button"
                    wire:click="generateTicket"
                    wire:loading.attr="disabled"
                    variant="primary"
                    icon="printer"
                    :disabled="$selectedClientId === null || $phoneStatus !== 'active'"
                    data-test="submit-ticket-button"
                >
                    Generar e imprimir
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showClientModal" class="w-full md:w-[50rem]">
        <form wire:submit="createClient" class="space-y-6">
            <div>
                <flux:heading size="lg">Crear cliente</flux:heading>
                <flux:text class="mt-2">
                    El cliente quedará seleccionado y su teléfono se considerará actualizado para generar el ticket.
                </flux:text>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="newDocument" label="Identidad" maxlength="13" class="sm:col-span-2" />
                <flux:input wire:model="newFirstName" label="Primer nombre" maxlength="50" />
                <flux:input wire:model="newSecondName" label="Segundo nombre" maxlength="50" />
                <flux:input wire:model="newFirstSurname" label="Primer apellido" maxlength="50" />
                <flux:input wire:model="newSecondSurname" label="Segundo apellido" maxlength="50" />
                <flux:input wire:model="newAge" type="number" min="1" max="120" label="Edad" />
                <flux:input wire:model="newPhone" inputmode="numeric" maxlength="8" label="Teléfono" />
                <flux:select wire:model="newGender" label="Género" placeholder="Selecciona una opción">
                    <flux:select.option value="MASCULINO">Masculino</flux:select.option>
                    <flux:select.option value="FEMENINO">Femenino</flux:select.option>
                </flux:select>
                <x-remote-select source="ticket-departments" cache="departments" wire:model.live="newDepartment" label="Departamento" placeholder="Selecciona un departamento" />
                <x-remote-select source="ticket-municipalities" cache="municipalities" :filters="['parent' => $newDepartment]" wire:model="newMunicipality"
                    label="Municipio"
                    placeholder="Selecciona un municipio"
                    :disabled="$newDepartment === ''"
                    class="sm:col-span-2" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeClientForm">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" icon="user-plus">Crear y seleccionar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
