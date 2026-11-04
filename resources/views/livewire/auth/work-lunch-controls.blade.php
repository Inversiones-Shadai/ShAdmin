<div>
    @if ($enabled)
        <flux:dropdown position="bottom" align="end">
            <flux:button
                variant="ghost"
                icon="clock"
                aria-label="Control de jornada laboral"
                title="{{ $statusLabel }}"
                data-test="work-lunch-menu-button"
            >
                <span class="hidden xl:inline">{{ $statusLabel }}</span>
            </flux:button>

            <flux:menu class="w-72">
                <div class="px-3 py-2">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Jornada de hoy</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $statusLabel }}</p>
                </div>

                @if ($loadError)
                    <div class="mx-2 mb-2 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">
                        {{ $loadError }}
                    </div>
                    <flux:menu.item
                        as="button"
                        icon="arrow-path"
                        wire:click="refreshState"
                        wire:loading.attr="disabled"
                        wire:target="refreshState"
                        data-test="refresh-work-lunch-button"
                    >
                        Actualizar estado
                    </flux:menu.item>
                @else
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 px-3 pb-2 text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Entrada</span>
                        <span class="text-right font-medium text-slate-700 dark:text-slate-200">{{ $workStart ?? '—' }}</span>
                        <span class="text-slate-500 dark:text-slate-400">Almuerzo</span>
                        <span class="text-right font-medium text-slate-700 dark:text-slate-200">
                            {{ $lunchStart ?? '—' }}@if ($lunchEnd) – {{ $lunchEnd }}@endif
                        </span>
                        <span class="text-slate-500 dark:text-slate-400">Salida</span>
                        <span class="text-right font-medium text-slate-700 dark:text-slate-200">{{ $workEnd ?? '—' }}</span>
                    </div>

                    <flux:menu.separator />

                    @if ($canWork)
                        <flux:menu.item
                            as="button"
                            icon="briefcase"
                            wire:click="registerWork"
                            wire:loading.attr="disabled"
                            wire:target="registerWork,registerLunch"
                            :disabled="$workDisabled"
                            data-test="register-work-button"
                        >
                            {{ $workLabel }}
                        </flux:menu.item>
                    @endif

                    @if ($canLunch)
                        <flux:menu.item
                            as="button"
                            icon="pause"
                            wire:click="registerLunch"
                            wire:loading.attr="disabled"
                            wire:target="registerWork,registerLunch"
                            :disabled="$lunchDisabled"
                            data-test="register-lunch-button"
                        >
                            {{ $lunchLabel }}
                        </flux:menu.item>
                    @endif
                @endif
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
