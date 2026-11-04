@props([
    'variant' => 'outline',
])

@php
    $classes = Flux::classes('shrink-0 object-contain dark:invert')->add(
        match ($variant) {
            'outline', 'solid' => '[:where(&)]:size-6',
            'mini' => '[:where(&)]:size-5',
            'micro' => '[:where(&)]:size-4',
        },
    );

    $source = 'data:image/png;base64,'.base64_encode(
        file_get_contents(resource_path('views/flux/icon/vista.png')),
    );
@endphp

<img
    {{ $attributes->class($classes) }}
    src="{{ $source }}"
    alt=""
    aria-hidden="true"
    data-flux-icon
    data-slot="icon"
/>
