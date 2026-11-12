<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><flux:heading size="xl">Licencias de escritorio</flux:heading><flux:text>Generación, cupos, vinculaciones y validaciones de HQ-Client, ShPOS y Administrator.</flux:text></div>
        @if ($access->allows('licenses.generate'))
            <div class="flex flex-wrap gap-2"><flux:button wire:click="createCustomer" icon="user-plus">Nuevo cliente</flux:button><flux:button wire:click="create" variant="primary" icon="key">Generar licencia</flux:button></div>
        @endif
    </div>
    <flux:callout icon="shield-check"><flux:callout.text>La primera activación requiere Internet. Cada inicio verifica la licencia. Ante un fallo de conexión se permiten hasta 72 horas desde la última validación. Los cupos desvinculados se reservan hasta vencer su autorización anterior para impedir uso simultáneo.</flux:callout.text></flux:callout>
    @if ($error)<flux:callout variant="danger"><flux:callout.text>{{ $error }}</flux:callout.text></flux:callout>@endif
    @if ($message)<flux:callout><flux:callout.text>{{ $message }}</flux:callout.text></flux:callout>@endif
    @if ($generatedKey)
        <div class="rounded-xl border border-green-400 bg-green-50 p-5 dark:bg-green-950">
            <flux:heading>Clave generada</flux:heading>
            <flux:text>Para volver a consultarla se requiere un código enviado al correo administrador.</flux:text>
            <p class="my-3 break-all font-mono select-all">{{ $generatedKey }}</p>
            <flux:button wire:click="$set('generatedKey', '')">Ya guardé la clave</flux:button>
        </div>
    @endif
    <section class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="lg">Clientes de licencias</flux:heading>@if ($filterCustomerId !== '')<flux:button size="sm" wire:click="selectCustomer">Limpiar selección</flux:button>@endif</div>
        <flux:input wire:model.live.debounce.400ms="customerSearch" placeholder="Buscar cliente" icon="magnifying-glass" aria-label="Buscar clientes de licencias" />
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm" aria-label="Clientes de licencias">
                <thead><tr class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
                    <th scope="col" class="px-4 py-3">Cliente</th>
                    <th scope="col" class="px-4 py-3 text-right">Licencias</th>
                    <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                </tr></thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr wire:key="license-customer-{{ $customer['id'] }}" @class(['border-b border-zinc-100 last:border-b-0 dark:border-zinc-800', 'bg-blue-50 dark:bg-blue-950' => $filterCustomerId === $customer['id']])>
                            <th scope="row" class="px-4 py-3 font-semibold">
                                <button type="button" wire:click="selectCustomer('{{ $customer['id'] }}')" class="text-left hover:underline" aria-label="Ver licencias de {{ $customer['name'] }}">{{ $customer['name'] }}</button>
                            </th>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $customer['license_count'] }}</td>
                            <td class="px-4 py-3"><div class="flex justify-end gap-2">
                                <flux:button size="sm" wire:click="selectCustomer('{{ $customer['id'] }}')" :variant="$filterCustomerId === $customer['id'] ? 'primary' : 'outline'" aria-label="Seleccionar cliente {{ $customer['name'] }}">{{ $filterCustomerId === $customer['id'] ? 'Seleccionado' : 'Ver licencias' }}</flux:button>
                                @if ($access->allows('licenses.update'))<flux:button size="sm" icon="pencil-square" wire:click="editCustomer('{{ $customer['id'] }}')" aria-label="Editar cliente {{ $customer['name'] }}" />@endif
                            </div></td>
                        </tr>
                    @empty<tr><td colspan="3" class="p-6 text-center text-zinc-500 dark:text-zinc-400">No se encontraron clientes.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between gap-2"><flux:button size="sm" wire:click="changeCustomerPage(-1)" :disabled="$customerPage <= 1">Anterior</flux:button><flux:text>Clientes · página {{ $customerPage }} de {{ $customerLastPage }}</flux:text><flux:button size="sm" wire:click="changeCustomerPage(1)" :disabled="$customerPage >= $customerLastPage">Siguiente</flux:button></div>
    </section>
    @if ($filterCustomerId === '')
        <flux:callout icon="user"><flux:callout.text>Selecciona un cliente para consultar sus licencias.</flux:callout.text></flux:callout>
    @else
    <flux:heading size="lg">Licencias de {{ $filterCustomerName }}</flux:heading>
    <flux:input wire:model.live.debounce.400ms="search" placeholder="Buscar licencia" icon="magnifying-glass" aria-label="Buscar licencias" />
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm"><thead><tr class="border-b bg-zinc-50 dark:bg-zinc-900">
            @foreach (['Nombre de licencia', 'Aplicación', 'Vigencia', 'Clave', 'Cupos ocupados', 'Vencimiento', 'Estado', 'Acciones'] as $column)<th class="px-4 py-3">{{ $column }}</th>@endforeach
        </tr></thead><tbody>
        @forelse (collect($licenses)->groupBy('customer_id') as $groupLicenses)
            <tr class="border-y border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800"><th colspan="8" scope="rowgroup" class="px-4 py-3 font-semibold">Cliente: {{ $groupLicenses->first()['customer_name'] }}</th></tr>
            @foreach ($groupLicenses as $license)
            <tr wire:key="license-{{ $license['id'] }}" class="border-b border-zinc-100 dark:border-zinc-800">
                <td class="max-w-64 break-words px-4 py-3">{{ $license['label'] }}</td><td class="px-4 py-3">{{ ['hq-client' => 'HQ-Client', 'shpos' => 'ShPOS', 'shpos-administrator' => 'ShPOS-Administrator'][$license['product']] ?? $license['product'] }}</td>
                <td class="px-4 py-3">{{ match ((int) ($license['term_months'] ?? 0)) { 1 => '1 mes', 6 => '6 meses', 12 => '1 año', default => 'Anterior' } }}</td>
                <td class="px-4 py-3"><span class="font-mono">…{{ $license['key_suffix'] }}</span>
                    @if ($access->allows('licenses.detail'))
                        @if ($license['can_reveal_key'] ?? false)
                            <flux:button size="sm" wire:click="requestKeyCode('{{ $license['id'] }}')" wire:loading.attr="disabled">Ver clave completa</flux:button>
                        @else
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Clave anterior no recuperable</p>
                        @endif
                    @endif
                </td><td class="px-4 py-3">{{ $license['used_seats'] }} / {{ $license['seats'] }}</td>
                <td class="px-4 py-3">{{ \Carbon\CarbonImmutable::parse($license['expires_at'], 'UTC')->setTimezone('America/Tegucigalpa')->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3"><flux:badge :color="!$license['enabled'] ? 'red' : (\Carbon\CarbonImmutable::parse($license['expires_at'], 'UTC')->isPast() ? 'amber' : 'green')">{{ !$license['enabled'] ? 'Suspendida' : (\Carbon\CarbonImmutable::parse($license['expires_at'], 'UTC')->isPast() ? 'Vencida' : 'Activa') }}</flux:badge></td>
                <td class="px-4 py-3"><div class="flex gap-2">
                    @if ($access->allows('licenses.detail'))<flux:button size="sm" wire:click="show('{{ $license['id'] }}')">Vinculaciones y validaciones</flux:button>@endif
                    @if ($access->allows('licenses.update'))<flux:button size="sm" wire:click="edit('{{ $license['id'] }}')">Modificar</flux:button>@endif
                    @if ($access->allows('licenses.delete') && ($license['can_delete'] ?? false))<flux:button size="sm" variant="danger" wire:click="delete('{{ $license['id'] }}')" wire:confirm="¿Eliminar esta licencia definitivamente? Su clave dejará de funcionar. El historial de auditoría se conservará." wire:loading.attr="disabled">Eliminar</flux:button>@endif
                </div></td>
            </tr>
            @endforeach
        @empty<tr><td colspan="8" class="p-8 text-center">No se encontraron licencias.</td></tr>@endforelse
        </tbody></table>
    </div>
    <div class="flex items-center justify-between"><flux:button wire:click="changePage(-1)" :disabled="$page <= 1">Anterior</flux:button><flux:text>Página {{ $page }} de {{ $lastPage }}</flux:text><flux:button wire:click="changePage(1)" :disabled="$page >= $lastPage">Siguiente</flux:button></div>
    @endif
    @if ($showKeyModal && $access->allows('licenses.detail'))
        <flux:modal wire:model="showKeyModal" class="w-full max-w-xl">
            <div class="space-y-5">
                <flux:heading size="lg">Ver clave completa</flux:heading>
                <flux:text>{{ $keyLicenseLabel }}</flux:text>
                @if ($revealedKey !== '')
                    <div wire:key="revealed-license-key" x-data="{ timer: null, destroy() { clearTimeout(this.timer) } }" x-init="timer = setTimeout(() => $wire.closeKeyReveal(), 60000)" class="space-y-3">
                        <flux:text>Consulta autorizada. La clave se ocultará en un minuto.</flux:text>
                        <p class="break-all rounded-lg bg-zinc-100 p-4 font-mono select-all dark:bg-zinc-800">{{ $revealedKey }}</p>
                    </div>
                @elseif ($keyChallengeId !== '')
                    <flux:text>Enviamos un código de seis dígitos al correo administrador. Vence en 10 minutos y permite cinco intentos. Solicita autorización al administrador e ingresa el código.</flux:text>
                    @if ($error)<flux:callout variant="danger"><flux:callout.text>{{ $error }}</flux:callout.text></flux:callout>@endif
                    <form wire:submit="revealKey" class="space-y-4">
                        <flux:input label="Código de autorización" wire:model="keyCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Autorizar y ver clave</flux:button>
                    </form>
                    <flux:button size="sm" wire:click="requestKeyCode('{{ $keyLicenseId }}')" wire:loading.attr="disabled">Solicitar otro código</flux:button>
                @else
                    <flux:text>La clave se ha ocultado. Solicita un nuevo código para volver a consultarla.</flux:text>
                @endif
                <flux:button wire:click="closeKeyReveal">Cerrar</flux:button>
            </div>
        </flux:modal>
    @endif
    @if ($showForm && ($editingId ? $access->allows('licenses.update') : $access->allows('licenses.generate')))
    <flux:modal wire:model="showForm" class="w-full max-w-xl">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Modificar licencia' : 'Generar licencia' }}</flux:heading>
            <flux:input label="Buscar cliente" wire:model.live.debounce.400ms="customerSearch" placeholder="Escribe el nombre del cliente" />
            <flux:select label="Cliente" wire:model.live="customerId" required>
                <option value="">Selecciona un cliente</option>
                @if ($customerId !== '' && ! collect($customers)->contains('id', $customerId))<option value="{{ $customerId }}">{{ $choiceCustomerName ?: 'Cliente seleccionado' }}</option>@endif
                @foreach ($customers as $customer)<option value="{{ $customer['id'] }}">{{ $customer['name'] }}</option>@endforeach
            </flux:select>
            <div class="flex justify-between gap-2"><flux:button size="sm" wire:click="changeCustomerPage(-1)" :disabled="$customerPage <= 1">Clientes anteriores</flux:button><flux:button size="sm" wire:click="changeCustomerPage(1)" :disabled="$customerPage >= $customerLastPage">Más clientes</flux:button></div>
            <flux:input label="Nombre de la licencia" wire:model="label" maxlength="160" required />
            <flux:select label="Aplicación" wire:model="product" :disabled="$editingId !== null"><option value="hq-client">HQ-Client</option><option value="shpos">ShPOS</option><option value="shpos-administrator">ShPOS-Administrator</option></flux:select>
            <flux:input label="Cupos de dispositivos" type="number" min="1" max="10000" wire:model="seats" required />
            <flux:text>Esta licencia solo permite activar la aplicación seleccionada.</flux:text>
            @if ($editingId)
                <flux:input label="Vencimiento actual (hora de Honduras)" :value="$expiresAt" readonly />
                <flux:select label="Renovar licencia" wire:model="renewalMonths"><option value="0">Conservar vencimiento actual</option><option value="1">Agregar 1 mes</option><option value="6">Agregar 6 meses</option><option value="12">Agregar 1 año</option></flux:select>
                <flux:text>Una licencia vigente conserva el tiempo restante. Si ya venció, el nuevo plazo comienza al renovar.</flux:text>
                <flux:checkbox label="Licencia habilitada" wire:model="enabled" />
            @else
                <flux:select label="Vigencia de la licencia" wire:model="termMonths"><option value="1">1 mes</option><option value="6">6 meses</option><option value="12">1 año</option></flux:select>
                <flux:text>La vigencia comienza al generar la licencia. La API central calcula el vencimiento por meses calendario.</flux:text>
            @endif
            @if (! $editingId)
                @if ($generationChallengeId !== '')
                    <flux:callout icon="envelope"><flux:callout.text>Enviamos un código al correo administrador para autorizar esta creación. Vence en 10 minutos y permite cinco intentos. Si cambias los datos, solicita un código nuevo.</flux:callout.text></flux:callout>
                    <flux:input label="Código para crear la licencia" wire:model="generationCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
                    <flux:button size="sm" wire:click="requestGenerationCode" wire:loading.attr="disabled">Solicitar otro código de creación</flux:button>
                @else
                    <flux:text>La creación requiere un código enviado al correo administrador. La clave se generará después de validar el código.</flux:text>
                @endif
            @endif
            @if ($error)<flux:callout variant="danger"><flux:callout.text>{{ $error }}</flux:callout.text></flux:callout>@endif
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ $editingId ? 'Guardar cambios' : ($generationChallengeId !== '' ? 'Autorizar y generar clave' : 'Solicitar código de creación') }}</flux:button>
        </form>
    </flux:modal>
    @endif
    @if ($showCustomerForm && ($editingCustomerId ? $access->allows('licenses.update') : $access->allows('licenses.generate')))
        <flux:modal wire:model="showCustomerForm" class="w-full max-w-lg">
            <form wire:submit="saveCustomer" class="space-y-5"><flux:heading size="lg">{{ $editingCustomerId ? 'Editar cliente' : 'Nuevo cliente de licencias' }}</flux:heading><flux:input label="Nombre del cliente" wire:model="customerName" maxlength="160" required /><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Guardar cliente</flux:button></form>
        </flux:modal>
    @endif
    @if ($detailId && $access->allows('licenses.detail'))
        <section class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex justify-between"><flux:heading size="lg">Vinculaciones y validaciones</flux:heading><flux:button size="sm" wire:click="closeDetails">Cerrar</flux:button></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr>@foreach (['Equipo', 'Huella', 'Última validación (UTC)', 'Reserva hasta (UTC)', 'Estado', ''] as $column)<th class="p-2">{{ $column }}</th>@endforeach</tr></thead><tbody>
                @forelse (data_get($details, 'devices.data', []) as $device)<tr wire:key="binding-{{ $device['id'] }}" class="border-t border-zinc-200 dark:border-zinc-700">
                    <td class="p-2">{{ $device['device_name'] }}</td><td class="p-2 font-mono" title="{{ $device['device_hash'] }}">{{ substr($device['device_hash'], 0, 12) }}…</td><td class="p-2">{{ $device['last_validated_at'] }}</td><td class="p-2">{{ $device['reserved_until'] }}</td><td class="p-2">{{ $device['revoked_at'] ? 'Desvinculado' : 'Vinculado' }}</td>
                    <td class="p-2">@if (!$device['revoked_at'] && $access->allows('licenses.unlink'))<flux:button size="sm" variant="danger" wire:click="unlink('{{ $device['id'] }}')" wire:confirm="¿Desvincular este equipo? Se bloqueará al validar; el cupo queda reservado hasta vencer su autorización sin conexión.">Desvincular</flux:button>@endif</td>
                </tr>@empty<tr><td colspan="6" class="p-4">Sin equipos vinculados.</td></tr>@endforelse
            </tbody></table></div>
            <div class="flex gap-3"><flux:button size="sm" wire:click="detailPage('devices', -1)" :disabled="$devicesPage <= 1">Anterior</flux:button><flux:text>Equipos · página {{ $devicesPage }}</flux:text><flux:button size="sm" wire:click="detailPage('devices', 1)" :disabled="$devicesPage >= data_get($details, 'devices.last_page', 1)">Siguiente</flux:button></div>
            <flux:heading>Historial de generación, validaciones y cambios</flux:heading>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Fecha (UTC)</th><th class="p-2">Acción</th><th class="p-2">Resultado</th><th class="p-2">Vinculación</th></tr></thead><tbody>
                @forelse (data_get($details, 'events.data', []) as $event)<tr class="border-t border-zinc-200 dark:border-zinc-700"><td class="p-2">{{ $event['created_at'] }}</td><td class="p-2">{{ $event['action'] }}</td><td class="p-2">{{ $event['result'] }}</td><td class="p-2 font-mono">{{ $event['activation_id'] ?? '—' }}</td></tr>@empty<tr><td colspan="4" class="p-4">Sin eventos.</td></tr>@endforelse
            </tbody></table></div>
            <div class="flex gap-3"><flux:button size="sm" wire:click="detailPage('events', -1)" :disabled="$eventsPage <= 1">Anterior</flux:button><flux:text>Historial · página {{ $eventsPage }}</flux:text><flux:button size="sm" wire:click="detailPage('events', 1)" :disabled="$eventsPage >= data_get($details, 'events.last_page', 1)">Siguiente</flux:button></div>
        </section>
    @endif
</div>
