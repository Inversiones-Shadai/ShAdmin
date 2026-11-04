<meta charset="utf-8" />
<meta name="shadmin-image-session" content="{{ hash_hmac('sha256', session()->getId().session('auth.api.token', ''), config('app.key')) }}" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? "$title - ".($appName ?? config('app.name', 'Laravel')) : ($appName ?? config('app.name', 'Laravel')) }}
</title>

@include('partials.app-icons')

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
