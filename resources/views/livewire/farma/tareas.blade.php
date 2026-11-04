<div class="farma-screen" data-test="tasks-screen">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><flux:heading size="xl">Tareas</flux:heading><flux:text>Crea y asigna tareas a un usuario POS y un stock. Consulta el estado de cada asignación.</flux:text></div>
        <div class="flex flex-wrap gap-2">
            @if ($access['filter'])<flux:button wire:click="openFilters" icon="adjustments-horizontal" data-test="task-filters-button">Filtros</flux:button>@endif
            <flux:button wire:click="goToPage({{ $page }})" icon="arrow-path" wire:loading.attr="disabled">Actualizar</flux:button>
            @if ($access['create'])<flux:button wire:click="openCreate" variant="primary" icon="plus" :disabled="$pendingMutation !== null">Crear tarea</flux:button>@endif
        </div>
    </div>
    @if ($access['filter'])
        <flux:modal wire:model.self="showFiltersModal" @close="cancelFilters" class="w-[calc(100vw-2rem)] min-w-0 max-w-3xl">
            <form wire:submit="applyFilters" class="space-y-5" data-test="task-filters-form">
                <div><flux:heading size="lg">Filtros de tareas</flux:heading><flux:text class="mt-1">Consulta por stock, usuario asignado, estado o búsqueda.</flux:text></div>
                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <x-remote-select source="task-stocks" cache="options.stocks" wire:model="stockFilter" label="Stock" placeholder="Todos los stocks" />
                    <x-remote-select source="task-users" cache="options.usuarios" wire:model="userFilter" label="Usuario asignado" placeholder="Todos los usuarios" />
                    <flux:select wire:model="stateFilter" label="Estado"><option value="">Todos los estados</option>@foreach ($states as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</flux:select>
                    <flux:input wire:model="search" label="Buscar tarea" icon="magnifying-glass" maxlength="200" />
                </div>
                @if ($loadError)<p role="alert" class="text-sm text-red-600 dark:text-red-400">{{ $loadError }}</p>@endif
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:button type="button" wire:click="clearFilters" icon="arrow-path">Limpiar filtros</flux:button>
                    <div class="flex gap-2">
                        <flux:button type="button" wire:click="cancelFilters">Cancelar</flux:button>
                        <flux:button type="submit" variant="primary" icon="funnel" wire:loading.attr="disabled" wire:target="applyFilters">Aplicar filtros</flux:button>
                    </div>
                </div>
            </form>
        </flux:modal>
    @endif
    @if ($loadError)<flux:callout variant="danger" heading="No se pudo completar la consulta">{{ $loadError }}</flux:callout>@endif
    <section class="farma-table-panel rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div class="farma-table-scroll" tabindex="0" role="region" aria-label="Tabla desplazable">
        <flux:table>
            <flux:table.columns>@foreach (['Tarea', 'Stock', 'Usuario asignado', 'Programación', 'Estado de hoy', 'Última actualización', 'Acciones'] as $label)<flux:table.column>{{ $label }}</flux:table.column>@endforeach</flux:table.columns>
            <flux:table.rows>
                @foreach ($records as $task)
                    <flux:table.row :key="$task['id']">
                        <flux:table.cell><strong>{{ $task['titulo'] }}</strong></flux:table.cell>
                        <flux:table.cell>{{ $task['stock_nombre'] }}</flux:table.cell>
                        <flux:table.cell>{{ $task['usuario_nombre'] }}</flux:table.cell>
                        <flux:table.cell><div class="space-y-1"><span>{{ match ($task['programacion'] ?? 'UNICA') {'DIARIA' => 'Diariamente', 'FECHAS' => 'Fechas específicas', default => 'Un solo día'} }}</span><flux:text class="text-xs">{{ isset($task['fecha_inicio']) ? \Carbon\Carbon::parse($task['fecha_inicio'])->format('d/m/Y') : 'Sin fecha (anterior)' }}@if (! empty($task['fecha_fin'])) al {{ \Carbon\Carbon::parse($task['fecha_fin'])->format('d/m/Y') }}@elseif (($task['programacion'] ?? '') === 'DIARIA') · Sin fecha de fin @endif</flux:text></div></flux:table.cell>
                        <flux:table.cell><flux:badge :color="match ($task['estado']) {'COMPLETADA' => 'green', 'EN_PROCESO' => 'blue', 'CANCELADA' => 'zinc', default => 'amber'}">{{ $task['estado'] !== 'CANCELADA' && isset($task['programada_hoy']) && ! $task['programada_hoy'] ? 'No programada hoy' : ($states[$task['estado']] ?? $task['estado']) }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ \Carbon\Carbon::parse($task['updated_at'], config('app.timezone'))->timezone('America/Tegucigalpa')->format('d/m/Y h:i A') }}</flux:table.cell>
                        <flux:table.cell><div class="flex gap-2">
                            @if ($access['show'])<flux:button wire:click="openDetail({{ $task['id'] }})" size="sm" aria-label="Ver tarea">Ver</flux:button>@endif
                            @if ($task['estado'] !== 'CANCELADA')
                                @if ($access['update'])<flux:button wire:click="openEdit({{ $task['id'] }})" size="sm" :disabled="$pendingMutation !== null">Modificar</flux:button>@endif
                                @if ($access['cancel'])<flux:button wire:click="confirmCancel({{ $task['id'] }})" size="sm" :disabled="$pendingMutation !== null">Cancelar tarea</flux:button>@endif
                            @endif
                        </div></flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        </div>
        @if ($records === [] && ! $loadError)<flux:text class="p-8 text-center">No hay tareas que coincidan con la consulta.</flux:text>@endif
        <div class="mt-4 flex items-center justify-between gap-4"><flux:text>{{ $total }} tareas · Página {{ $page }} de {{ $lastPage }}</flux:text><div class="flex gap-2"><flux:button wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Anterior</flux:button><flux:button wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Siguiente</flux:button></div></div>
    </section>
    <flux:modal wire:model.self="showEditor" class="md:w-[42rem]" :dismissible="$pendingMutation === null" :closable="$pendingMutation === null">
        <form wire:submit="save" class="space-y-5">
            <div><flux:heading size="lg">{{ $editingId === null ? 'Crear y asignar tarea' : 'Modificar tarea' }}</flux:heading><flux:text>La tarea aparecerá únicamente al usuario asignado cuando utilice este stock.</flux:text></div>
            <flux:input wire:model="title" label="Título" maxlength="200" :disabled="$pendingMutation !== null" />
            <flux:textarea wire:model="description" label="Descripción" rows="4" maxlength="4000" :disabled="$pendingMutation !== null" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-remote-select source="task-stocks" cache="options.stocks" wire:model="stockId" label="Stock" :disabled="$pendingMutation !== null" />
                <x-remote-select source="task-users" cache="options.usuarios" wire:model="userId" label="Usuario POS" :disabled="$pendingMutation !== null" />
            </div>
            <flux:select wire:model.live="schedule" label="Programación" :disabled="$pendingMutation !== null">
                <option value="UNICA">Un solo día</option><option value="DIARIA">Diariamente</option><option value="FECHAS">Fechas específicas</option>
            </flux:select>
            @if ($schedule === 'FECHAS')
                <div class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-test="task-calendar">
                    <div class="flex items-center justify-between gap-2"><flux:button type="button" wire:click="changeMonth(-1)" size="sm" aria-label="Mes anterior" :disabled="$pendingMutation !== null">‹</flux:button><strong class="capitalize">{{ $calendarLabel }}</strong><flux:button type="button" wire:click="changeMonth(1)" size="sm" aria-label="Mes siguiente" :disabled="$pendingMutation !== null">›</flux:button></div>
                    <div class="grid grid-cols-7 gap-1 text-center">
                        @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday)<span class="py-2 text-xs font-semibold">{{ $weekday }}</span>@endforeach
                        @foreach ($calendar as $day)
                            <button type="button" wire:key="date-{{ $day['date'] }}" wire:click="toggleDate('{{ $day['date'] }}')" aria-label="{{ $day['date'] }}" aria-pressed="{{ $day['selected'] ? 'true' : 'false' }}" @disabled($pendingMutation !== null) @class(['rounded-lg py-2 text-sm transition disabled:opacity-50', 'bg-blue-600 font-bold text-white' => $day['selected'], 'hover:bg-slate-100 dark:hover:bg-slate-800' => ! $day['selected'], 'opacity-40' => ! $day['inMonth']])>{{ $day['day'] }}</button>
                        @endforeach
                    </div>
                    <flux:text>{{ count($scheduledDates) }} fechas seleccionadas. Cada fecha tendrá su propio estado.</flux:text>
                    <div class="flex max-h-24 flex-wrap gap-2 overflow-y-auto">@foreach ($scheduledDates as $date)<button type="button" wire:click="toggleDate('{{ $date }}')" @disabled($pendingMutation !== null) class="rounded-full bg-blue-100 px-3 py-1 text-xs text-blue-900" aria-label="Quitar {{ $date }}">{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} ×</button>@endforeach</div>
                    @error('scheduledDates')<p class="text-sm text-red-600">Selecciona al menos una fecha (máximo 366).</p>@enderror
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input type="date" wire:model="startDate" :label="$schedule === 'DIARIA' ? 'Desde' : 'Fecha de la tarea'" :disabled="$pendingMutation !== null" />
                    @if ($schedule === 'DIARIA')<flux:input type="date" wire:model="endDate" label="Hasta (opcional)" :disabled="$pendingMutation !== null" />@endif
                </div>
                @if ($schedule === 'DIARIA')<flux:text>Se repetirá todos los días desde la fecha elegida. Completar un día no completa los siguientes.</flux:text>@endif
            @endif
            <flux:text>ShPOS mostrará las tareas del día según la fecha de Honduras, solo al usuario y stock asignados.</flux:text>
            @if ($editingId !== null)<flux:text>Los cambios se aplican desde hoy. Se conserva el historial de fechas anteriores.</flux:text>@endif
            @error('operation')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
            @if ($pendingMutation !== null)<flux:text>Si la respuesta no llegó, reintenta para confirmar el mismo guardado.</flux:text>@endif
            <div class="flex justify-end gap-2"><flux:button type="button" wire:click="$set('showEditor', false)" :disabled="$pendingMutation !== null">Cerrar</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ $pendingMutation === null ? 'Guardar tarea' : 'Reintentar guardado' }}</flux:button></div>
        </form>
    </flux:modal>
    <flux:modal wire:model.self="showDetail" class="md:w-[42rem]">
        <div class="space-y-4"><flux:heading size="lg">{{ $selectedTask['titulo'] ?? 'Tarea' }}</flux:heading><p class="whitespace-pre-wrap break-words">{{ $selectedTask['descripcion'] ?? '' }}</p><flux:text>{{ $selectedTask['stock_nombre'] ?? '' }} · {{ $selectedTask['usuario_nombre'] ?? '' }}</flux:text>
            <flux:text>Programación: {{ match ($selectedTask['programacion'] ?? 'UNICA') {'DIARIA' => 'Diariamente', 'FECHAS' => 'Fechas específicas', default => 'Un solo día'} }} · {{ $selectedTask['fecha_inicio'] ?? 'Sin fecha (anterior)' }}@if (! empty($selectedTask['fecha_fin'])) al {{ $selectedTask['fecha_fin'] }}@endif</flux:text>
            @if (! empty($selectedTask['dias']))<div class="max-h-64 space-y-2 overflow-y-auto"><flux:heading>Fechas y estados</flux:heading>@foreach ($selectedTask['dias'] as $day)<div class="flex justify-between gap-4 border-b border-slate-200 py-2 text-sm dark:border-slate-700"><span>{{ $day['fecha'] ? \Carbon\Carbon::parse($day['fecha'])->format('d/m/Y') : 'Sin fecha' }}</span><span>{{ $states[$day['estado']] ?? $day['estado'] }}{{ ! $day['activo'] ? ' · Retirada' : '' }}</span></div>@endforeach</div>@endif
            <flux:button wire:click="$set('showDetail', false)">Cerrar</flux:button></div>
    </flux:modal>
    <flux:modal wire:model.self="showCancel" class="md:w-96" :dismissible="$pendingMutation === null" :closable="$pendingMutation === null">
        <div class="space-y-4"><flux:heading size="lg">Cancelar tarea</flux:heading><flux:text>¿Cancelar «{{ $selectedTask['titulo'] ?? '' }}»? Se conservará en el historial y dejará de mostrarse en ShPOS.</flux:text>@error('operation')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror<div class="flex justify-end gap-2"><flux:button wire:click="$set('showCancel', false)" :disabled="$pendingMutation !== null">Volver</flux:button><flux:button wire:click="cancelTask" variant="primary" wire:loading.attr="disabled">{{ $pendingMutation === null ? 'Cancelar tarea' : 'Reintentar' }}</flux:button></div></div>
    </flux:modal>
</div>
