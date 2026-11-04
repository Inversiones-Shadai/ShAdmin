@props(['status' => ''])

<flux:badge
    :color="App\Support\ProductStatus::color((string) $status)"
    size="sm"
    {{ $attributes->class('max-w-full whitespace-normal text-left leading-5') }}
>
    {{ trim((string) $status) !== '' ? $status : 'Sin estado' }}
</flux:badge>
