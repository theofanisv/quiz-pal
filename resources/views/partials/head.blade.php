@php
    $pageTitle = filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel');
    $description ??= __('Create a room, share the link or QR code, and play together on your phones.');
    $shareImage = asset('og-image.png');
    $currentLocale = \App\Enums\Locale::current();
@endphp
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $description }}">
@isset($robots)
<meta name="robots" content="{{ $robots }}">
@endisset
@isset($canonical)
<link rel="canonical" href="{{ $canonical }}">
@endisset
@isset($alternates)
@foreach ($alternates as $languageCode => $alternateUrl)
<link rel="alternate" hreflang="{{ $languageCode }}" href="{{ $alternateUrl }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ \App\Enums\Locale::default()->homeUrl() }}">
@endisset

<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical ?? url()->current() }}">
<meta property="og:image" content="{{ $shareImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ config('app.name') }}">
<meta property="og:locale" content="{{ $currentLocale->openGraphLocale() }}">
@foreach (\App\Enums\Locale::cases() as $locale)
@if ($locale !== $currentLocale)
<meta property="og:locale:alternate" content="{{ $locale->openGraphLocale() }}">
@endif
@endforeach
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $shareImage }}">
@isset($structuredData)
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endisset

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
