@props(['fixedViewport' => false])

<x-layouts::app.sidebar :title="$title ?? null" :fixed-viewport="$fixedViewport">
    <flux:main @class(['flex min-h-0 flex-1 flex-col py-0!' => $fixedViewport])>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
