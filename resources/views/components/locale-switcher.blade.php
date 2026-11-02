@php
    $current = \App\Enums\Locale::current();
    // Each language has its own home page, so the switcher links straight to it there.
    $onHomePage = request()->routeIs('home', 'home.localized');
@endphp

<nav aria-label="{{ __('Language') }}" class="flex items-center gap-1 rounded-full border border-night-700 bg-night-800 p-1 text-sm">
    @foreach (\App\Enums\Locale::cases() as $locale)
        <a
            href="{{ $onHomePage ? $locale->homeUrl() : route('locale.switch', $locale) }}"
            @class([
                'rounded-full px-3 py-1 font-medium transition',
                'bg-neon-cyan text-night-950' => $current === $locale,
                'text-ink-muted hover:text-ink' => $current !== $locale,
            ])
            @if ($current === $locale) aria-current="true" @endif
        >
            {{ $locale->label() }}
        </a>
    @endforeach
</nav>
