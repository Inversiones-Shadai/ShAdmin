<section class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700" data-test="transfer-matrix-products">
    <div class="space-y-1 border-b border-slate-200 p-4 dark:border-slate-700">
        <flux:heading>Productos de la matriz de inventario</flux:heading>
        <flux:text>Elige los lotes del stock de origen y la cantidad a enviar. Puedes usar varios lotes por producto o cubrir solo parte del faltante.</flux:text>
        <flux:text>Origen: {{ $options['stock_actual']['descripcion'] ?? 'Stock de esta sesión' }}</flux:text>
    </div>
    <div class="farma-table-scroll overflow-x-auto" tabindex="0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left dark:bg-slate-800/50">
                <tr><th class="px-4 py-3">Producto</th><th class="px-4 py-3 text-right">Faltante en matriz</th><th class="px-4 py-3 text-right">Cantidad preparada</th><th class="px-4 py-3 text-right">Por cubrir</th><th class="px-4 py-3">Lotes</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($matrixLines as $product)
                    <tr wire:key="matrix-transfer-product-{{ $product['id'] }}">
                        <td class="px-4 py-3"><p class="font-semibold">{{ $product['descripcion'] }}</p><p class="text-xs text-slate-500">{{ $product['codigo'] }}</p></td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $product['punto_reorden'], 4) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($product['asignado']) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($product['pendiente'], 4) }}</td>
                        <td class="px-4 py-3"><flux:button wire:click="openMatrixProduct({{ $product['id'] }})" size="sm" icon="plus" wire:loading.attr="disabled">Seleccionar lote y cantidad</flux:button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 p-3 dark:border-slate-700"><flux:button :href="route('farma.stocks.matriz-inventario')" variant="ghost" size="sm">Volver a la matriz</flux:button></div>
</section>
