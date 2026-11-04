<div class="space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Reportes</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">Jornada laboral</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
                Consulta entradas, salidas y almuerzos, y detecta automáticamente jornadas incompletas o almuerzos excedidos.
            </p>
        </div>

        @if ($filterActive)
            <div class="flex flex-wrap items-center gap-2">
                <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300">
                    {{ number_format($total) }} {{ $total === 1 ? 'jornada' : 'jornadas' }}
                </div>
                @if ($access['export'] && $total > 0)
                    <flux:button
                        wire:click="openExport"
                        variant="primary"
                        icon="arrow-down-tray"
                        data-test="export-work-lunch-report-button"
                    >
                        Exportar
                    </flux:button>
                @endif
            </div>
        @endif
    </section>

    @if ($loadError)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="No fue posible generar el reporte">
            {{ $loadError }}
        </flux:callout>
    @endif

    <form wire:submit="applyFilters" class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div>
            <h2 class="font-semibold text-slate-950 dark:text-white">Criterios del reporte</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Selecciona un trabajador y el período que deseas revisar.
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(18rem,1.5fr)_repeat(2,minmax(10rem,1fr))]">
            <section class="space-y-3 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Trabajador</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Selecciona un trabajador de la lista.</p>
                </div>

                <x-remote-select source="report-users" cache="users" wire:model="selectedUser" placeholder="Selecciona un trabajador" />
                @error('selectedUser')
                    <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </section>

            <flux:input wire:model="fromDate" type="date" label="Desde" icon="calendar-days" />
            <flux:input wire:model="toDate" type="date" label="Hasta" icon="calendar-days" />
        </div>

        <div class="grid gap-1 sm:grid-cols-2">
            @error('fromDate')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            @error('toDate')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end dark:border-slate-800">
            @if ($filterActive)
                <flux:button type="button" wire:click="clearFilters">
                    Limpiar reporte
                </flux:button>
            @endif
            <flux:button type="submit" variant="primary" icon="funnel" data-test="filter-work-lunch-report-button">
                Generar reporte
            </flux:button>
        </div>
    </form>

    @if ($filterActive && $schedule === null)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="El trabajador no tiene horario asignado">
            Se muestran los registros de asistencia, pero no es posible evaluar el cumplimiento hasta configurar sus horas laborales y minutos de almuerzo.
            @if ($scheduleError)
                <p class="mt-2 text-sm">{{ $scheduleError }}</p>
            @endif
        </flux:callout>
    @endif

    @if ($filterActive)
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen del reporte">
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">Total de registros</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ number_format($total) }}</p>
            </article>
            <article class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm dark:border-red-950 dark:bg-red-950/30">
                <p class="text-sm text-red-700 dark:text-red-300">Jornada incompleta</p>
                <p class="mt-2 text-2xl font-semibold text-red-800 dark:text-red-200">{{ $shortWorkCount }}</p>
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">En esta página</p>
            </article>
            <article class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-950 dark:bg-amber-950/30">
                <p class="text-sm text-amber-700 dark:text-amber-300">Almuerzo excedido</p>
                <p class="mt-2 text-2xl font-semibold text-amber-800 dark:text-amber-200">{{ $longLunchCount }}</p>
                <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">En esta página</p>
            </article>
            <article class="rounded-xl border border-blue-200 bg-blue-50 p-5 shadow-sm dark:border-blue-950 dark:bg-blue-950/30">
                <p class="text-sm text-blue-700 dark:text-blue-300">Horario asignado</p>
                @if ($schedule)
                    <p class="mt-2 text-lg font-semibold text-blue-900 dark:text-blue-100">
                        {{ intdiv($schedule['work_minutes'], 60) }} h de trabajo
                    </p>
                    <p class="mt-1 text-xs text-blue-600 dark:text-blue-400">
                        {{ $schedule['lunch_minutes'] }} min de almuerzo
                    </p>
                @else
                    <p class="mt-2 text-lg font-semibold text-blue-900 dark:text-blue-100">Sin configurar</p>
                @endif
            </article>
        </section>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <h2 class="font-semibold text-slate-950 dark:text-white">Registros de jornada</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                @if ($filterActive)
                    Mostrando {{ count($jornadas) }} de {{ number_format($total) }} registros.
                @else
                    Define un trabajador y un rango de fechas para consultar el reporte.
                @endif
            </p>
        </div>

        @if (! $filterActive)
            <div class="px-6 py-14 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-300">
                    <flux:icon.clock class="size-6" />
                </div>
                <h3 class="mt-4 font-medium text-slate-950 dark:text-white">El reporte está listo para consultar</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Selecciona el trabajador, el período y presiona “Generar reporte”.
                </p>
            </div>
        @elseif ($jornadas === [])
            <div class="px-6 py-14 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    <flux:icon.magnifying-glass class="size-6" />
                </div>
                <h3 class="mt-4 font-medium text-slate-950 dark:text-white">No se encontraron jornadas</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Ajusta el trabajador o el rango de fechas e intenta nuevamente.
                </p>
            </div>
        @else
            <div class="overflow-x-auto px-4 pb-4 pt-2 sm:px-6" data-test="work-lunch-report-table">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="py-3.5">Fecha</flux:table.column>
                        <flux:table.column class="py-3.5">Trabajador</flux:table.column>
                        <flux:table.column class="py-3.5">Ubicación</flux:table.column>
                        <flux:table.column class="py-3.5">Entrada</flux:table.column>
                        <flux:table.column class="py-3.5">Salida</flux:table.column>
                        <flux:table.column class="py-3.5">Jornada neta</flux:table.column>
                        <flux:table.column class="py-3.5">Almuerzo</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($jornadas as $jornada)
                            <flux:table.row :key="$jornada['id']">
                                <flux:table.cell class="whitespace-nowrap py-4 font-medium text-slate-950 dark:text-white">
                                    {{ $jornada['date'] ?: '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="min-w-44 py-4">
                                    {{ $jornada['user'] ?: '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="min-w-52 py-4">
                                    <div class="font-medium text-slate-950 dark:text-white">{{ $jornada['stock'] ?: '—' }}</div>
                                    @if ($jornada['device'])
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $jornada['device'] }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap py-4">{{ $jornada['work_start'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap py-4">{{ $jornada['work_end'] ?: '—' }}</flux:table.cell>
                                <flux:table.cell class="min-w-44 py-4">
                                    <div class="font-mono font-semibold text-slate-950 dark:text-white">
                                        {{ $jornada['net_work_duration'] ?: '—' }}
                                    </div>
                                    <div class="mt-2">
                                        @if ($jornada['work_status'] === 'short')
                                            <flux:badge color="red" icon="exclamation-circle">
                                                Faltaron {{ $jornada['work_deficit_minutes'] }} min
                                            </flux:badge>
                                        @elseif ($jornada['work_status'] === 'compliant')
                                            <flux:badge color="green" icon="check-circle">Cumplida</flux:badge>
                                        @elseif ($jornada['work_status'] === 'pending')
                                            <flux:badge color="blue" icon="clock">En curso</flux:badge>
                                        @else
                                            <flux:badge color="zinc">Sin horario</flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="min-w-52 py-4">
                                    <div class="font-mono font-semibold text-slate-950 dark:text-white">
                                        {{ $jornada['lunch_duration'] ?: '—' }}
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $jornada['lunch_start'] ?: '—' }} – {{ $jornada['lunch_end'] ?: '—' }}
                                    </div>
                                    <div class="mt-2">
                                        @if ($jornada['lunch_status'] === 'exceeded')
                                            <flux:badge color="amber" icon="exclamation-triangle">
                                                Excedió {{ $jornada['lunch_excess_minutes'] }} min
                                            </flux:badge>
                                        @elseif ($jornada['lunch_status'] === 'compliant')
                                            <flux:badge color="green" icon="check-circle">Dentro del tiempo</flux:badge>
                                        @elseif ($jornada['lunch_status'] === 'pending')
                                            <flux:badge color="blue" icon="clock">En almuerzo</flux:badge>
                                        @elseif ($jornada['lunch_status'] === 'not_taken')
                                            <flux:badge color="zinc">Sin almuerzo</flux:badge>
                                        @else
                                            <flux:badge color="zinc">Sin horario</flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif

        @if ($filterActive && $lastPage > 1)
            <nav class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-700" aria-label="Paginación del reporte">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Página {{ $page }} de {{ $lastPage }}
                </p>

                <div class="flex items-center gap-2">
                    <flux:button wire:click="previousPage" icon="chevron-left" size="sm" :disabled="$page <= 1">
                        Anterior
                    </flux:button>
                    <flux:button wire:click="nextPage" icon-trailing="chevron-right" size="sm" :disabled="$page >= $lastPage">
                        Siguiente
                    </flux:button>
                </div>
            </nav>
        @endif
    </section>

    <flux:modal wire:model.self="showExportModal" class="w-full md:w-[32rem]">
        <form wire:submit="exportReport" class="space-y-6">
            <div>
                <flux:heading size="lg">Exportar jornada laboral</flux:heading>
                <flux:text class="mt-2">
                    Se exportarán los {{ number_format($total) }} registros que coinciden con el trabajador y período seleccionados.
                </flux:text>
            </div>

            <flux:radio.group wire:model="exportFormat" label="Selecciona el formato" variant="segmented">
                <flux:radio value="pdf" icon="document-text">PDF</flux:radio>
                <flux:radio value="xlsx" icon="table-cells">Excel</flux:radio>
            </flux:radio.group>
            @error('exportFormat')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-950/50 dark:text-slate-300">
                <p class="font-medium text-slate-900 dark:text-white">Contenido incluido</p>
                <p class="mt-1">
                    Entrada, salida, jornada neta, cumplimiento, inicio y fin de almuerzo, duración y exceso de tiempo.
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeExport">
                    Cancelar
                </flux:button>
                <flux:button
                    type="submit"
                    variant="primary"
                    icon="arrow-down-tray"
                    wire:loading.attr="disabled"
                    wire:target="exportReport"
                >
                    <span wire:loading.remove wire:target="exportReport">Descargar</span>
                    <span wire:loading wire:target="exportReport">Generando...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
