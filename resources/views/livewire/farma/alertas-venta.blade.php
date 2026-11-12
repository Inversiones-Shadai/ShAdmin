<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl">Alertas de venta</flux:heading>
            <flux:text>Define los requisitos que caja verá al agregar un producto a la factura.</flux:text>
        </div>
        @can('api-permission', 'sale-alerts.manage')
            <flux:button variant="primary" wire:click="start" wire:loading.attr="disabled">Nueva alerta</flux:button>
        @endcan
    </div>

    @if (session('sale-alert-status'))
        <p role="status" class="text-sm text-green-700 dark:text-green-300">{{ session('sale-alert-status') }}</p>
    @endif
    @error('list') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror

    @if ($editing)
        <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" wire:key="alert-form-{{ $alertId ?? 'new' }}">
            <flux:heading size="lg">{{ $alertId === null ? 'Nueva alerta' : 'Editar alerta' }}</flux:heading>
            <flux:input wire:model="title" label="Título" maxlength="150" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="target" label="Aplicar a">
                    <flux:select.option value="producto">Un producto</flux:select.option>
                    <flux:select.option value="categoria">Una categoría</flux:select.option>
                </flux:select>
                @if ($target === 'producto')
                    <x-remote-select source="sale-alert-products" cache="productChoice" wire:model="productId" label="Producto" required />
                @else
                    <x-remote-select source="sale-alert-categories" cache="categoryChoice" wire:model="categoryId" label="Categoría" required />
                @endif
                <flux:select wire:model.live="scope" label="Alcance">
                    <flux:select.option value="todos">Todos los stocks</flux:select.option>
                    <flux:select.option value="stock">Un stock específico</flux:select.option>
                </flux:select>
                @if ($scope === 'stock')
                    <x-remote-select source="sale-alert-stocks" cache="stockChoice" wire:model="stockId" label="Stock" required />
                @endif
            </div>
            <flux:textarea wire:model="requirements" label="Requisitos de venta" description="Escribe un requisito por línea (máximo 15)." rows="5" maxlength="4800" required />
            <flux:checkbox wire:model="active" label="Alerta activa" />
            @error('save') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex flex-wrap gap-2">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Guardar alerta</flux:button>
                <flux:button type="button" wire:click="cancel" wire:loading.attr="disabled">Cancelar</flux:button>
            </div>
        </form>
    @endif

    <div class="space-y-3">
        @forelse ($alerts as $alert)
            <article wire:key="sale-alert-{{ $alert['id'] }}" class="space-y-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading>{{ $alert['titulo'] }}</flux:heading>
                        <flux:text>
                            {{ $alert['producto_id'] !== null ? 'Producto: '.($alert['producto'] ?? '#'.$alert['producto_id']) : 'Categoría: '.($alert['categoria'] ?? '#'.$alert['categoria_id']) }}
                            · {{ $alert['stock_id'] === null ? 'Todos los stocks' : ($alert['stock'] ?? 'Stock #'.$alert['stock_id']) }}
                            · {{ $alert['activo'] ? 'Activa' : 'Inactiva' }}
                        </flux:text>
                    </div>
                    @can('api-permission', 'sale-alerts.manage')
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="start('{{ $alert['id'] }}')" wire:loading.attr="disabled">Editar</flux:button>
                            <flux:button size="sm" wire:click="remove('{{ $alert['id'] }}')" wire:confirm="¿Retirar esta alerta de venta?" wire:loading.attr="disabled">Retirar</flux:button>
                        </div>
                    @endcan
                </div>
                <ol class="list-decimal space-y-1 pl-5 text-sm">
                    @foreach ($alert['requisitos'] as $requirement)
                        <li class="break-words">{{ $requirement }}</li>
                    @endforeach
                </ol>
            </article>
        @empty
            <flux:text>No hay alertas de venta registradas.</flux:text>
        @endforelse
    </div>

    @if ($lastPage > 1)
        <nav aria-label="Páginas de alertas" class="flex flex-wrap items-center gap-3">
            <flux:button wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Anterior</flux:button>
            <flux:text>Página {{ $page }} de {{ $lastPage }}</flux:text>
            <flux:button wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Siguiente</flux:button>
        </nav>
    @endif
</div>
