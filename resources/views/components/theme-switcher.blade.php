<flux:dropdown x-data position="bottom" align="end">
    <flux:button variant="ghost" square aria-label="Cambiar tema" title="Cambiar tema" data-test="theme-switcher">
        <flux:icon.sun class="size-5 dark:hidden" />
        <flux:icon.moon class="hidden size-5 dark:block" />
    </flux:button>

    <flux:menu>
        <flux:menu.radio.group x-model="$flux.appearance" aria-label="Tema de ShAdmin">
            <flux:menu.radio value="light" icon="sun">Claro</flux:menu.radio>
            <flux:menu.radio value="dark" icon="moon">Oscuro</flux:menu.radio>
            <flux:menu.radio value="system" icon="computer-desktop">Según el sistema</flux:menu.radio>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
