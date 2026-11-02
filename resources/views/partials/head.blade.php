<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="theme-color" content="#0b1020">

@fonts

<meta name="reverb" content="{{ json_encode(config('broadcasting.connections.reverb.client')) }}">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@if ($appearance ?? true)
    @fluxAppearance
@endif
