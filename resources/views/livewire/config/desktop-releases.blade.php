<div class="space-y-6">
    @php
        $products = ['shpos' => 'ShPOS', 'shpos-administrator' => 'ShPOS Administrator', 'hq-client' => 'HQ-Client'];
        $states = ['draft' => 'Borrador', 'pilot' => 'Piloto', 'stable' => 'Estable', 'withdrawn' => 'Retirada'];
        $actions = ['create' => 'Borrador creado', 'edit' => 'Borrador editado', 'pilots' => 'Pilotos asignados', 'pilot' => 'Publicada en piloto', 'stable' => 'Promovida a estable', 'withdraw' => 'Versión retirada'];
    @endphp
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><flux:heading size="xl">Actualizaciones de escritorio</flux:heading><flux:text>Versiones de cada aplicación: borrador, piloto y publicación estable.</flux:text></div>
        @if ($access->allows('desktop-releases.create'))<flux:button variant="primary" icon="plus" wire:click="create" :disabled="$pending !== null">Nuevo borrador</flux:button>@endif
    </div>
    @if ($error)<flux:callout variant="danger" icon="exclamation-triangle"><flux:callout.text>{{ $error }}</flux:callout.text></flux:callout>@endif
    @if ($message)<flux:callout variant="success" icon="check-circle"><flux:callout.text>{{ $message }}</flux:callout.text></flux:callout>@endif
    @if ($pending)
        <flux:callout variant="warning" icon="clock"><flux:callout.heading>Confirmación pendiente</flux:callout.heading><flux:callout.text>Reintenta para confirmar el mismo cambio antes de iniciar otro. La solicitud se conserva al volver a esta pantalla.</flux:callout.text>
            @if ($access->allows('desktop-releases.'.$pending['action']))<flux:button class="mt-3" wire:click="retry" wire:loading.attr="disabled">Reintentar</flux:button>@endif
        </flux:callout>
    @endif
    <div class="grid gap-4 md:grid-cols-3">
        <flux:select label="Aplicación" wire:model.live="product"><option value="">Todas</option>@foreach ($products as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach</flux:select>
        <flux:select label="Estado" wire:model.live="status"><option value="">Todos</option>@foreach ($states as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach</flux:select>
        <flux:input label="Buscar versión" wire:model.live.debounce.400ms="search" placeholder="Ejemplo: 1.2" />
    </div>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm"><thead><tr class="border-b bg-zinc-50 dark:bg-zinc-900">@foreach (['Aplicación', 'Versión', 'Arquitectura', 'Estado', 'Pilotos', 'Política', 'Acciones'] as $column)<th scope="col" class="px-4 py-3">{{ $column }}</th>@endforeach</tr></thead><tbody>
            @forelse ($releases as $release)
                <tr wire:key="release-{{ $release['id'] }}" class="border-b border-zinc-100 dark:border-zinc-800">
                    <td class="px-4 py-3">{{ $products[$release['product']] ?? $release['product'] }}</td>
                    <td class="px-4 py-3 font-mono">{{ $release['version'] }}</td><td class="px-4 py-3 uppercase">{{ $release['architecture'] }}</td>
                    <td class="px-4 py-3"><flux:badge :color="match ($release['status']) { 'stable' => 'green', 'pilot' => 'blue', 'withdrawn' => 'red', default => 'zinc' }">{{ $states[$release['status']] }}</flux:badge>
                        @if ($release['current_pilot'] || $release['current_stable'])<div class="mt-1 text-xs text-zinc-500">Versión vigente del canal</div>@elseif (in_array($release['status'], ['pilot', 'stable']))<div class="mt-1 text-xs text-zinc-500">Histórica</div>@endif
                    </td>
                    <td class="px-4 py-3">{{ $release['pilot_count'] }}</td><td class="px-4 py-3">{{ $release['required'] ? 'Requerida' : 'Recomendada' }}</td>
                    <td class="px-4 py-3"><div class="flex flex-wrap gap-2">
                        <flux:button size="sm" wire:click="detail('{{ $release['id'] }}')">Ver</flux:button>
                        @if ($release['status'] === 'draft' && $access->allows('desktop-releases.edit'))<flux:button size="sm" wire:click="edit('{{ $release['id'] }}')" :disabled="$pending !== null">Editar</flux:button>@endif
                        @if (in_array($release['status'], ['draft', 'pilot']) && $access->allows('desktop-releases.pilots'))<flux:button size="sm" wire:click="pilots('{{ $release['id'] }}')" :disabled="$pending !== null">Equipos piloto</flux:button>@endif
                        @if ($release['status'] === 'draft' && $access->allows('desktop-releases.pilot'))<flux:button size="sm" wire:click="requestTransition('{{ $release['id'] }}', 'pilot')" :disabled="$pending !== null">Publicar en piloto</flux:button>@endif
                        @if ($release['status'] === 'pilot' && $release['current_pilot'] && $access->allows('desktop-releases.stable'))<flux:button size="sm" wire:click="requestTransition('{{ $release['id'] }}', 'stable')" :disabled="$pending !== null">Promover a estable</flux:button>@endif
                        @if ($release['status'] !== 'withdrawn' && $access->allows('desktop-releases.withdraw'))<flux:button size="sm" variant="danger" wire:click="requestTransition('{{ $release['id'] }}', 'withdraw')" :disabled="$pending !== null">Retirar</flux:button>@endif
                        @if ($access->allows('desktop-releases.audit'))<flux:button size="sm" wire:click="audit('{{ $release['id'] }}')">Auditoría</flux:button>@endif
                    </div></td>
                </tr>
            @empty<tr><td colspan="7" class="p-8 text-center">No hay versiones registradas con estos filtros.</td></tr>@endforelse
        </tbody></table>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3"><flux:button wire:click="changePage(-1)" :disabled="$page <= 1">Anterior</flux:button><flux:text>Página {{ $page }} de {{ $lastPage }}</flux:text><flux:button wire:click="changePage(1)" :disabled="$page >= $lastPage">Siguiente</flux:button></div>
    @if ($detailId && ($detail = collect($releases)->firstWhere('id', $detailId)))
        <section class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex items-center justify-between gap-3"><flux:heading size="lg">{{ $products[$detail['product']] }} · {{ $detail['version'] }}</flux:heading><flux:button size="sm" wire:click="closeDetail">Cerrar</flux:button></div>
            <div class="whitespace-pre-wrap break-words text-sm">{{ $detail['notes'] }}</div>
            <dl class="grid gap-3 text-sm sm:grid-cols-3"><div><dt class="text-zinc-500">Contrato API mínimo</dt><dd>{{ $detail['min_api_contract'] }}</dd></div><div><dt class="text-zinc-500">Esquema SQL mínimo</dt><dd>{{ $detail['min_schema_version'] }}</dd></div><div><dt class="text-zinc-500">Esquema SQL máximo</dt><dd>{{ $detail['max_schema_version'] ?? 'Sin límite adicional' }}</dd></div></dl>
            <flux:text>Revisión {{ $detail['revision'] }} · {{ $detail['required'] ? 'Actualización requerida' : 'Actualización recomendada' }}</flux:text>
        </section>
    @endif
    @if ($showForm && $access->allows($editingId ? 'desktop-releases.edit' : 'desktop-releases.create'))
        <flux:modal wire:model="showForm" class="w-full max-w-2xl"><form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Editar borrador' : 'Nuevo borrador' }}</flux:heading>
            @if (!$editingId)
                <flux:input label="Buscar paquete por versión" wire:model.live.debounce.400ms="artifactSearch" :disabled="$pending !== null" />
                <flux:select label="Paquete verificado" wire:model="artifactId" required :disabled="$pending !== null"><option value="">Selecciona un paquete firmado y preparado</option>
                    @if ($artifactId !== '' && !collect($artifacts)->contains('id', $artifactId))<option value="{{ $artifactId }}">Paquete seleccionado</option>@endif
                    @foreach ($artifacts as $artifact)<option value="{{ $artifact['id'] }}">{{ $products[$artifact['product']] }} · {{ $artifact['version'] }} · {{ strtoupper($artifact['architecture']) }}</option>@endforeach
                </flux:select>
                @if ($artifacts === [])<flux:text>No hay paquetes disponibles. Primero prepara, firma y registra el paquete desde el proceso de publicación.</flux:text>@endif
                <div class="flex justify-between gap-3"><flux:button size="sm" wire:click="changeArtifactPage(-1)" :disabled="$artifactPage <= 1">Paquetes anteriores</flux:button><flux:text>{{ $artifactPage }} / {{ $artifactLastPage }}</flux:text><flux:button size="sm" wire:click="changeArtifactPage(1)" :disabled="$artifactPage >= $artifactLastPage">Más paquetes</flux:button></div>
            @endif
            <flux:textarea label="Notas de versión" wire:model="notes" rows="5" maxlength="10000" required :disabled="$pending !== null" />
            <div class="grid gap-4 sm:grid-cols-3"><flux:input label="Contrato API mínimo" wire:model="minApi" type="number" min="1" max="65535" required :disabled="$pending !== null" /><flux:input label="Esquema SQL mínimo" wire:model="minSchema" type="number" min="0" max="65535" required :disabled="$pending !== null" /><flux:input label="Esquema SQL máximo" wire:model="maxSchema" type="number" min="0" max="65535" placeholder="Sin límite" :disabled="$pending !== null" /></div>
            <flux:checkbox label="Marcar como actualización requerida" wire:model="requiredUpdate" :disabled="$pending !== null" />
            <flux:text>El paquete y su versión quedan fijos. Las migraciones de base de datos se despliegan por separado.</flux:text>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" :disabled="$pending !== null">Guardar borrador</flux:button>
        </form></flux:modal>
    @endif
    @if ($showPilots && $access->allows('desktop-releases.pilots'))
        <flux:modal wire:model="showPilots" class="w-full max-w-2xl"><div class="space-y-5">
            <flux:heading size="lg">Equipos piloto</flux:heading><flux:text>Solo activaciones vigentes de la misma aplicación. La API vuelve a validar la licencia al guardar y publicar.</flux:text>
            <flux:input label="Buscar equipo o licencia" wire:model.live.debounce.400ms="deviceSearch" :disabled="$pending !== null" />
            <div class="space-y-3">@forelse ($devices as $device)<label wire:key="pilot-choice-{{ $device['id'] }}" class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"><input type="checkbox" wire:model="selectedDevices" value="{{ $device['id'] }}" @disabled($pending !== null) /><span><span class="block font-medium">{{ $device['device_name'] }}</span><span class="text-sm text-zinc-500">{{ $device['license_label'] }}</span></span></label>@empty<flux:text>No hay equipos disponibles con licencia vigente para esta aplicación.</flux:text>@endforelse</div>
            <div class="flex justify-between gap-3"><flux:button size="sm" wire:click="changeDevicePage(-1)" :disabled="$devicePage <= 1">Anterior</flux:button><flux:text>{{ $devicePage }} / {{ $deviceLastPage }}</flux:text><flux:button size="sm" wire:click="changeDevicePage(1)" :disabled="$devicePage >= $deviceLastPage">Siguiente</flux:button></div>
            <flux:text>{{ count($selectedDevices) }} equipos seleccionados</flux:text>
            @foreach (array_diff($selectedDevices, array_column($devices, 'id')) as $selected)<div class="flex items-center justify-between gap-3 text-sm"><span class="break-all">Selección de otra página o no disponible: {{ $selected }}</span><flux:button size="sm" wire:click="removeDevice('{{ $selected }}')" :disabled="$pending !== null">Quitar</flux:button></div>@endforeach
            <flux:button variant="primary" wire:click="savePilots" wire:loading.attr="disabled" :disabled="$pending !== null">Guardar equipos piloto</flux:button>
        </div></flux:modal>
    @endif
    @if ($showTransition && $access->allows('desktop-releases.'.$transitionAction))
        <flux:modal wire:model="showTransition" class="w-full max-w-lg"><form wire:submit="confirmTransition" class="space-y-5">
            <flux:heading size="lg">{{ ['pilot' => 'Publicar en piloto', 'stable' => 'Promover a estable', 'withdraw' => 'Retirar versión'][$transitionAction] ?? 'Confirmar cambio' }}</flux:heading>
            @if ($chosen = collect($releases)->firstWhere('id', $transitionId))<flux:text>{{ $products[$chosen['product']] }} · {{ $chosen['version'] }} · {{ strtoupper($chosen['architecture']) }}</flux:text>@endif
            <flux:text>{{ match ($transitionAction) { 'pilot' => 'Esta versión quedará disponible para los equipos piloto asignados.', 'stable' => 'Confirma que el piloto fue verificado. Esta versión pasará a ser la estable vigente de esta aplicación y arquitectura.', default => 'Se detendrán nuevas autorizaciones para esta versión. Los paquetes ya descargados y las instalaciones existentes se conservan.' } }}</flux:text>
            @if ($transitionAction === 'withdraw')<flux:textarea label="Motivo del retiro" wire:model="reason" maxlength="500" required :disabled="$pending !== null" />@endif
            <flux:button type="submit" :variant="$transitionAction === 'withdraw' ? 'danger' : 'primary'" wire:loading.attr="disabled" :disabled="$pending !== null">Confirmar</flux:button>
        </form></flux:modal>
    @endif
    @if ($auditId && $access->allows('desktop-releases.audit'))
        <section class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700"><div class="flex justify-between gap-3"><flux:heading size="lg">Auditoría de versión</flux:heading><flux:button size="sm" wire:click="closeAudit">Cerrar</flux:button></div>
            @forelse ($events as $event)
                @php($snapshot = is_string($event['snapshot']) ? json_decode($event['snapshot'], true) : $event['snapshot'])
                <div wire:key="release-event-{{ $event['id'] }}" class="space-y-1 border-b border-zinc-100 py-3 text-sm dark:border-zinc-800"><div class="font-medium">{{ $actions[$event['action']] ?? $event['action'] }} · Revisión {{ $event['revision'] }}</div><div class="text-zinc-500">{{ \Carbon\CarbonImmutable::parse($event['created_at'], 'UTC')->setTimezone('America/Tegucigalpa')->format('d/m/Y H:i') }} · Usuario {{ $event['actor_id'] }}</div>@if (data_get($snapshot, 'reason'))<div class="whitespace-pre-wrap break-words">{{ $snapshot['reason'] }}</div>@endif<details><summary class="cursor-pointer">Ver registro</summary><pre class="mt-2 overflow-auto whitespace-pre-wrap break-words">{{ json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details></div>
            @empty<flux:text>Sin eventos.</flux:text>@endforelse
            <div class="flex justify-between gap-3"><flux:button size="sm" wire:click="changeEventPage(-1)" :disabled="$eventPage <= 1">Anterior</flux:button><flux:text>{{ $eventPage }} / {{ $eventLastPage }}</flux:text><flux:button size="sm" wire:click="changeEventPage(1)" :disabled="$eventPage >= $eventLastPage">Siguiente</flux:button></div>
        </section>
    @endif
</div>
