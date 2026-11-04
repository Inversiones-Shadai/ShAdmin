<div class="farma-screen" data-test="supplier-rules-screen">
    <header>
        <p class="mb-2 text-sm font-medium text-cyan-700 dark:text-cyan-300">Farma · Proveedores</p>
        <flux:heading size="xl">Reglas de proveedores</flux:heading>
        <flux:text class="mt-2">Define las condiciones de devolución por vencimiento de cada proveedor.</flux:text>
    </header>
    <section class="farma-table-panel overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 p-4 dark:border-slate-800">
            <flux:text>{{ number_format($total) }} proveedores</flux:text>
            <flux:input wire:model.live.debounce.500ms="search" label="Buscar proveedor" icon="magnifying-glass" placeholder="Nombre del proveedor" maxlength="100" class="sm:max-w-sm" />
        </div>
        @if ($loadError)<div class="p-4"><flux:callout variant="danger">{{ $loadError }}</flux:callout></div>@endif
        <div class="farma-table-scroll overflow-x-auto" tabindex="0">
            <table class="min-w-full text-sm">
                <thead><tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500 dark:border-slate-800"><th class="p-4">Proveedor</th><th class="p-4">Anticipación de envío</th><th class="p-4">Devolución</th><th class="p-4 text-right">Reglas</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($providers as $provider)
                        <tr wire:key="supplier-rule-{{ $provider['id'] }}">
                            <td class="p-4 font-semibold">{{ $provider['nombre'] }}</td>
                            <td class="p-4">{{ isset($provider['revision']) ? $provider['meses_anticipacion'].' meses antes del vencimiento' : 'Sin configurar' }}</td>
                            <td class="p-4">{{ match ($provider['tipo_devolucion'] ?? '') { 'directa' => 'Directa', 'autorizacion' => 'Con autorización previa', 'no_acepta' => 'No acepta', default => 'Sin configurar' } }}</td>
                            <td class="p-4 text-right"><flux:button wire:click="openPolicy({{ $provider['id'] }})" size="sm" icon="adjustments-horizontal">{{ $canSave ? 'Configurar' : 'Consultar' }}</flux:button></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-10 text-center text-slate-500">{{ $loadError ? 'Consulta no disponible.' : 'No se encontraron proveedores.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800">
            <flux:text>Página {{ $page }} de {{ $lastPage }}</flux:text>
            <div class="flex gap-2"><flux:button wire:click="loadProviders({{ $page - 1 }})" size="sm" :disabled="$page <= 1" wire:loading.attr="disabled">Anterior</flux:button><flux:button wire:click="loadProviders({{ $page + 1 }})" size="sm" :disabled="$page >= $lastPage" wire:loading.attr="disabled">Siguiente</flux:button></div>
        </div>
    </section>
    <flux:modal wire:model.self="showForm" class="w-full md:max-w-2xl">
        <form wire:submit="save" class="space-y-5">
            <div><flux:heading size="lg">{{ $providerName }}</flux:heading><flux:text class="mt-1">Condiciones para devolver productos próximos a vencer.</flux:text></div>
            @if ($revision === 0)<flux:callout variant="warning">Este proveedor aún no tiene reglas registradas. Confirma sus condiciones antes de guardarlas.</flux:callout>@endif
            @if ($saveError)<flux:callout variant="danger">{{ $saveError }}</flux:callout>@endif
            <fieldset class="space-y-5" @disabled(! $canSave)>
                <flux:input wire:model="months" label="Meses de anticipación para enviar" type="number" min="0" max="60" description="Fecha límite de envío = vencimiento menos estos meses. La fecha límite está incluida; 0 permite enviar hasta antes del vencimiento." />
                <flux:select wire:model="returnType" label="Cómo acepta las devoluciones">
                    <option value="">Seleccionar condición</option><option value="directa">Acepta devolución directa</option><option value="autorizacion">Requiere autorización previa</option><option value="no_acepta">No acepta devoluciones</option>
                </flux:select>
                <div class="space-y-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <flux:checkbox wire:model="acceptsChildren" label="Acepta productos hijos / empezados" description="El producto fraccionado o empezado se registra como hijo. Se aplica una sola condición." />
                    <p class="text-sm text-slate-500 dark:text-slate-400">Los productos marcados como bonificación no aceptan devolución.</p>
                </div>
                <flux:textarea wire:model="notes" label="Observaciones del proveedor" maxlength="2000" rows="3" description="Condiciones adicionales que deben revisarse al gestionar la devolución." />
            </fieldset>
            <div class="flex justify-end gap-3"><flux:button type="button" wire:click="$set('showForm', false)">Cerrar</flux:button>@if ($canSave)<flux:button type="submit" variant="primary" wire:loading.attr="disabled">Guardar reglas</flux:button>@endif</div>
        </form>
    </flux:modal>
</div>
