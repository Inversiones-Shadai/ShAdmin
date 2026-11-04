@props(['source', 'label' => '', 'placeholder' => 'Seleccionar', 'cache' => null, 'filters' => [], 'disabled' => false, 'required' => false])
@php
    $model = $attributes->wire('model');
    $identity = 'selector-natural-'.hash('sha256', $source.'|'.$model->value.'|'.json_encode($filters).'|'.(int) $disabled);
    $component = $__env->shared('__livewire');
    $selected = $component && $cache ? collect(data_get($component, $cache, []))->first(fn ($option) => (string) ($option['id'] ?? '') === (string) data_get($component, $model->value)) : null;
    $choiceLabel = $selected['label'] ?? $selected['nombre'] ?? $selected['descripcion'] ?? '';
@endphp
{{-- Keep x-data stable when Livewire refreshes the cached choice during selection validation. --}}
<div wire:key="{{ $identity }}" x-data="remoteSelect({ source: @js($source), url: @js(route('ui.selectors', $source)), value: $wire.entangle(@js($model->value)){{ $model->hasModifier('live') ? '.live' : '' }}, label: $el.dataset.selectedLabel, cache: @js($cache), filters: @js($filters) })" {{ $attributes->except(['wire:model', 'wire:model.live', 'wire:key'])->class('relative min-w-0 space-y-2') }} @keydown.escape="if (open) { $event.preventDefault(); $event.stopPropagation(); close(true); }" @click.outside="close()" data-remote-select="{{ $source }}" data-selected-label="{{ $choiceLabel }}">
    @if ($label)<label class="block text-sm font-medium" for="{{ $identity }}">{{ $label }}{{ $required ? ' *' : '' }}</label>@endif
    <button x-ref="trigger" id="{{ $identity }}" type="button" @disabled($disabled) @click="open ? close(true) : show()" :aria-expanded="open" aria-haspopup="dialog" aria-controls="{{ $identity }}-panel" aria-label="{{ $label ?: $attributes->get('aria-label', $placeholder) }}" class="flex min-h-10 w-full items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-sm disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800">
        <span wire:ignore class="truncate" x-text="label || @js($placeholder)">{{ $choiceLabel ?: $placeholder }}</span><span aria-hidden="true">⌕</span>
    </button>
    <div wire:ignore x-ref="panel" id="{{ $identity }}-panel" popover="manual" @toggle="popoverToggled($event)" x-show="open" x-cloak role="dialog" aria-label="{{ $label ?: $placeholder }}" class="fixed z-50 m-0 space-y-3 overflow-y-auto overscroll-contain rounded-lg border border-slate-300 bg-white p-3 shadow-lg dark:border-slate-600 dark:bg-slate-900">
        <input x-ref="search" x-model="search" @input.debounce.350ms="load(1)" @keydown.enter.prevent="load(1)" type="search" maxlength="255" placeholder="Buscar…" aria-label="Buscar {{ $label }}" class="w-full shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:bg-slate-800" />
        <p x-show="loading" role="status" class="shrink-0 text-sm text-slate-500">Cargando…</p>
        <div x-show="error" role="alert" class="shrink-0 space-y-2 text-sm text-red-600"><p x-text="error"></p><button type="button" @click="load(page)" class="underline">Reintentar</button></div>
        <div x-ref="options" x-show="!loading && !error" class="max-h-64 space-y-1 overflow-y-auto overscroll-contain">
            <template x-for="item in items" :key="item.id"><button type="button" @click="select(item)" :disabled="selecting" class="block w-full rounded px-2 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700" :aria-pressed="String(value) === String(item.id)" x-text="item.label"></button></template>
            <p x-show="items.length === 0" class="text-sm text-slate-500">Sin resultados.</p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 text-xs"><span x-text="`${total} resultados · ${page} / ${lastPage}`"></span><div class="flex gap-3"><button type="button" @click="load(page - 1)" :disabled="loading || page <= 1">Anterior</button><button type="button" @click="load(page + 1)" :disabled="loading || page >= lastPage">Siguiente</button></div></div>
        <div class="flex shrink-0 justify-between gap-3 text-sm"><button type="button" @click="clear()">Limpiar</button><button type="button" @click="close(true)">Cerrar</button></div>
    </div>
    @error($model->value)<p class="text-sm text-red-600">{{ $message }}</p>@enderror
</div>
