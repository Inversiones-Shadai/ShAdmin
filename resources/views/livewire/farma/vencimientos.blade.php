<div class="farma-screen" data-test="expiry-screen">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><p class="mb-2 text-sm font-medium text-cyan-700 dark:text-cyan-300">Farma · Productos</p><flux:heading size="xl">Próximos a vencer</flux:heading><flux:text class="mt-2">Busca un producto, selecciona sus lotes y consulta las condiciones de devolución de cada lote.</flux:text></div>
        @if ($access['rules'])<flux:button :href="route('farma.reglas-proveedores')" wire:navigate icon="adjustments-horizontal">Reglas de proveedores</flux:button>@endif
    </header>
    <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="md:col-span-2"><flux:input wire:model.live.debounce.500ms="search" label="Buscar producto" icon="magnifying-glass" placeholder="Código, código de barras o descripción" maxlength="100" /></div>
        <flux:select wire:model.live="months" label="Vencimiento"><option value="">Todos los lotes</option><option value="3">Vencidos y próximos 3 meses</option><option value="6">Vencidos y próximos 6 meses</option><option value="12">Vencidos y próximos 12 meses</option><option value="24">Vencidos y próximos 24 meses</option></flux:select>
    </div>
    <div class="farma-screen-body space-y-6">

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">

            @if ($loadError)<div class="p-4"><flux:callout variant="danger">{{ $loadError }}</flux:callout></div>@endif
            <div class="farma-table-scroll overflow-x-auto" tabindex="0">
                <table class="min-w-full text-sm">
                    <thead><tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500 dark:border-slate-800"><th class="p-4">Producto</th><th class="p-4">Código / Barras</th><th class="p-4">Próximo vencimiento</th><th class="p-4 text-right">Lotes</th><th class="p-4"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($products as $product)
                            <tr wire:key="expiry-product-{{ $product['producto_id'] }}">
                                <td class="p-4"><div class="flex min-w-64 items-center gap-3">
                                    <div class="grid h-16 w-20 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-50 dark:bg-slate-800">
                                        @if ($access['image'] && ! empty($product['tiene_imagen']))
                                            <img data-session-image-src="{{ ($product['imagen_url'] ?? route('farma.products.preview', ['product' => $product['producto_id']])) }}" alt="{{ $product['descripcion'] }}" loading="lazy" class="h-16 w-full object-contain p-1" />
                                            <flux:icon.photo data-session-image-placeholder class="hidden size-8 text-slate-300" />
                                        @else<flux:icon.photo class="size-8 text-slate-300" />@endif
                                    </div><strong>{{ $product['descripcion'] }}</strong>
                                </div></td>
                                <td class="p-4 font-mono text-xs"><p>{{ $product['codigo'] ?: '—' }}</p><p class="mt-1 text-slate-500">{{ $product['codigobar'] ?: 'Sin código de barras' }}</p></td>
                                <td class="whitespace-nowrap p-4">{{ ! empty($product['proxima_expiracion']) ? Illuminate\Support\Carbon::parse($product['proxima_expiracion'])->format('d/m/Y') : 'Sin fecha' }}</td>
                                <td class="p-4 text-right">{{ $product['lotes_count'] }}</td>
                                <td class="p-4 text-right">@if ($access['lots'])<flux:button wire:click="openLots({{ $product['producto_id'] }})" size="sm" icon="list-bullet" wire:loading.attr="disabled">Seleccionar lotes</flux:button>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-10 text-center text-slate-500">{{ $loadError ? 'Consulta no disponible.' : 'No se encontraron productos con lotes para esta búsqueda.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 p-4 dark:border-slate-800">
                <flux:text>{{ number_format($total) }} productos · Página {{ $page }} de {{ $lastPage }}</flux:text><div class="flex gap-2"><flux:button wire:click="loadProducts({{ $page - 1 }})" size="sm" :disabled="$page <= 1" wire:loading.attr="disabled">Anterior</flux:button><flux:button wire:click="loadProducts({{ $page + 1 }})" size="sm" :disabled="$page >= $lastPage" wire:loading.attr="disabled">Siguiente</flux:button></div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><flux:heading size="lg">Lotes seleccionados ({{ count($selectedLots) }})</flux:heading><flux:text>Agrega lotes de varios productos. Los hijos / empezados se identifican automáticamente.</flux:text></div>@if ($selectedLots !== [] && $access['lots'])<flux:button wire:click="clearSelection" size="sm" variant="ghost">Limpiar lista</flux:button>@endif</div>
            @if ($selectedLots === [])
                <p class="py-8 text-center text-sm text-slate-500">Selecciona un producto y agrega los lotes que deseas revisar.</p>
            @else
                <div class="farma-table-scroll mt-4 overflow-x-auto" tabindex="0"><table class="min-w-full text-sm"><thead><tr class="text-left text-xs uppercase text-slate-500"><th class="p-3">Producto / Lote</th><th class="p-3">Proveedor</th><th class="p-3">Vencimiento</th><th class="p-3">Bonificación</th><th class="p-3">Presentación</th><th class="p-3"></th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($selectedLots as $id => $lot)
                        <tr wire:key="expiry-selected-{{ $id }}"><td class="min-w-48 p-3"><strong>{{ $lot['descripcion'] }}</strong><p class="mt-1 text-xs text-slate-500">{{ $lot['codigo'] }} · Lote {{ $lot['lote'] }}{{ ! empty($lot['es_hijo']) ? ' · Producto hijo' : '' }}</p></td><td class="p-3"><p>{{ $lot['proveedor_nombre'] ?? 'Sin proveedor identificado' }}</p></td><td class="whitespace-nowrap p-3">{{ ! empty($lot['fecha_expiracion']) ? Illuminate\Support\Carbon::parse($lot['fecha_expiracion'])->format('d/m/Y') : 'Sin fecha' }}</td><td class="p-3">{{ ! empty($lot['es_bonificacion']) ? 'Sí · Sin devolución' : 'No' }}</td><td class="p-3">{{ ! empty($lot['es_hijo']) ? 'Hijo / empezado' : 'Padre / completo' }}</td><td class="p-3">@if ($access['lots'])<flux:button wire:click="removeLot({{ $id }})" size="sm" variant="ghost" icon="x-mark" aria-label="Quitar lote {{ $lot['lote'] }}" />@endif</td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
            @if ($verifyError)<div class="mt-4"><flux:callout variant="danger">{{ $verifyError }}</flux:callout></div>@endif
            <div class="mt-4 flex justify-end">@if ($access['verify'])<flux:button wire:click="verify" variant="primary" icon="clipboard-document-check" :disabled="$selectedLots === []" wire:loading.attr="disabled">Verificar lista de lotes</flux:button>@endif</div>
        </section>

        @if ($results !== [])
            <section class="space-y-4" data-test="expiry-results">
                <div><flux:heading size="lg">Resultado de la verificación</flux:heading><flux:text>Consultado el {{ Illuminate\Support\Carbon::parse($verifiedAt)->timezone('America/Tegucigalpa')->format('d/m/Y H:i') }}. Esta consulta permite preparar la gestión con el proveedor.</flux:text></div>
                @foreach ($results as $result)
                    @php
                        $status = $result['estado'];
                        $color = match ($status) { 'apto' => 'green', 'no_apto' => 'red', 'requiere_autorizacion' => 'blue', default => 'amber' };
                        $label = match ($status) { 'apto' => 'Apto para devolución directa', 'no_apto' => 'No cumple las reglas', 'requiere_autorizacion' => 'Requiere autorización', default => 'Pendiente de confirmar' };
                    @endphp
                    <article wire:key="expiry-result-{{ $result['id'] }}" class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-semibold">{{ $result['descripcion'] ?? 'Lote no disponible' }} · {{ $result['lote'] ?? '#'.$result['id'] }}</h3><p class="mt-1 text-sm text-slate-500">{{ $result['proveedor_nombre'] ?? 'Sin proveedor' }}{{ ! empty($result['es_hijo']) ? ' · Producto hijo' : '' }}</p></div><flux:badge :color="$color">{{ $label }}</flux:badge></div>
                        <dl class="mt-4 grid gap-4 rounded-lg bg-slate-50 p-4 text-sm sm:grid-cols-2 xl:grid-cols-3 dark:bg-slate-800/60" data-test="expiry-rule-summary">
                            <div><dt class="text-xs text-slate-500 dark:text-slate-400">Fecha límite de envío</dt><dd class="mt-1 font-semibold">{{ ! empty($result['fecha_limite_envio']) ? Illuminate\Support\Carbon::parse($result['fecha_limite_envio'])->format('d/m/Y') : 'Por definir' }}</dd>@if (isset($result['regla']['meses_anticipacion']))<p class="mt-1 text-xs text-slate-500">{{ $result['regla']['meses_anticipacion'] }} meses antes del vencimiento</p>@endif</div>
                            <div><dt class="text-xs text-slate-500 dark:text-slate-400">Vencimiento</dt><dd class="mt-1 font-semibold">{{ ! empty($result['fecha_expiracion']) ? Illuminate\Support\Carbon::parse($result['fecha_expiracion'])->format('d/m/Y') : 'Sin fecha' }}</dd></div>
                            <div><dt class="text-xs text-slate-500 dark:text-slate-400">Es bonificación</dt><dd class="mt-1 font-semibold {{ ! empty($result['es_bonificacion']) ? 'text-red-600 dark:text-red-400' : '' }}">{{ ! empty($result['es_bonificacion']) ? 'Sí · No acepta devolución' : 'No' }}</dd></div>
                            <div><dt class="text-xs text-slate-500 dark:text-slate-400">Acepta productos hijos / empezados</dt><dd class="mt-1 font-semibold">{{ isset($result['regla']['acepta_hijos']) ? ($result['regla']['acepta_hijos'] ? 'Sí' : 'No') : 'Sin reglas configuradas' }}</dd></div>
                            <div><dt class="text-xs text-slate-500 dark:text-slate-400">Devolución del proveedor</dt><dd class="mt-1 font-semibold">{{ match (data_get($result, 'regla.tipo_devolucion')) { 'directa' => 'Directa', 'autorizacion' => 'Con autorización previa', 'no_acepta' => 'No acepta', default => 'Sin reglas configuradas' } }}</dd></div>
                        </dl>
                        <ul class="mt-3 list-inside list-disc space-y-1 text-sm">@foreach ($result['motivos'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                        @if (! empty($result['regla']['notas']))<p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800"><strong>Observaciones del proveedor:</strong> {{ $result['regla']['notas'] }}</p>@endif
                    </article>
                @endforeach
            </section>
        @endif

        <flux:modal wire:model.self="showLots" class="w-full md:max-w-5xl">
            <div class="space-y-5">
                <div><flux:heading size="lg">Lotes de {{ $activeProduct['descripcion'] ?? 'producto' }}</flux:heading><flux:text class="mt-1">Selecciona el lote por su proveedor y vencimiento.</flux:text></div>
                @if ($lotError)<flux:callout variant="danger">{{ $lotError }}</flux:callout>@endif
                <div class="farma-table-scroll overflow-x-auto" tabindex="0"><table class="min-w-full text-sm"><thead><tr class="text-left text-xs uppercase text-slate-500"><th class="p-3">Lote</th><th class="p-3">Vencimiento</th><th class="p-3">Proveedor</th><th class="p-3">Bonificación</th><th class="p-3"></th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($lots as $lot)
                        <tr wire:key="expiry-lot-{{ $lot['id'] }}"><td class="p-3 font-semibold">{{ $lot['lote'] }}@if (! empty($lot['es_hijo']))<p class="mt-1 text-xs font-normal text-slate-500">Producto hijo</p>@endif</td><td class="whitespace-nowrap p-3">{{ ! empty($lot['fecha_expiracion']) ? Illuminate\Support\Carbon::parse($lot['fecha_expiracion'])->format('d/m/Y') : 'Sin fecha' }}</td><td class="p-3"><p>{{ $lot['proveedor_nombre'] ?? 'Sin proveedor identificado' }}</p></td><td class="p-3">{{ ! empty($lot['es_bonificacion']) ? 'Sí · Sin devolución' : 'No' }}</td><td class="p-3"><flux:button wire:click="addLot({{ $lot['id'] }})" size="sm" :disabled="isset($selectedLots[$lot['id']])" wire:loading.attr="disabled">{{ isset($selectedLots[$lot['id']]) ? 'Agregado' : 'Agregar a la lista' }}</flux:button></td></tr>
                    @empty<tr><td colspan="5" class="p-6 text-center text-slate-500">{{ $lotError ? 'No se pudieron cargar los lotes.' : 'El producto no tiene lotes registrados.' }}</td></tr>@endforelse
                </tbody></table></div>
                <div class="flex flex-wrap items-center justify-between gap-3"><flux:text>Página {{ $lotPage }} de {{ $lotLastPage }}</flux:text><div class="flex gap-2"><flux:button wire:click="loadLots({{ $lotPage - 1 }})" size="sm" :disabled="$lotPage <= 1" wire:loading.attr="disabled">Anterior</flux:button><flux:button wire:click="loadLots({{ $lotPage + 1 }})" size="sm" :disabled="$lotPage >= $lotLastPage" wire:loading.attr="disabled">Siguiente</flux:button><flux:button wire:click="$set('showLots', false)" variant="primary" size="sm">Listo ({{ count($selectedLots) }})</flux:button></div></div>
            </div>
        </flux:modal>
    </div>
</div>
