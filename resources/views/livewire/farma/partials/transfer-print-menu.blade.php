<flux:dropdown position="bottom" align="end">
    <flux:button size="sm" variant="ghost" icon="printer" aria-label="Imprimir transferencia" title="Imprimir transferencia" data-test="print-transfer" />
    <flux:menu>
        <flux:menu.item wire:click="printTransfer({{ $printId }}, 'thermal')" icon="printer">Térmica (80 mm)</flux:menu.item>
        <flux:menu.item wire:click="printTransfer({{ $printId }}, 'a4')" icon="document-text">Papel A4</flux:menu.item>
    </flux:menu>
</flux:dropdown>
