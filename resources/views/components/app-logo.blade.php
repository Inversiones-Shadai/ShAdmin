@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand aria-label="Inicio de Farmacias Shadai" {{ $attributes }}>
        <x-slot name="logo" class="shadai-navigation-logo">
            <x-shadai-logo />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand aria-label="Inicio de Farmacias Shadai" {{ $attributes }}>
        <x-slot name="logo" class="shadai-navigation-logo">
            <x-shadai-logo />
        </x-slot>
    </flux:brand>
@endif
