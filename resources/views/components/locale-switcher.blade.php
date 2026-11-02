@php
    $current = app()->getLocale();
@endphp

<nav aria-label="{{ __('Language') }}" class="flex items-center gap-1 rounded-full border border-night-700 bg-night-800 p-1 text-sm">
    @foreach (\App\Enums\Locale::cases() as $locale)
        <a
            href="{{ route('locale.switch', $locale) }}"
            @class([
                'rounded-full px-3 py-1 font-medium transition',
                'bg-neon-cyan text-night-950' => $current === $locale->value,
                'text-ink-muted hover:text-ink' => $current !== $locale->value,
            ])
            @if ($current === $locale->value) aria-current="true" @endif
        >
            {{ $locale->label() }}
        </a>
    @endforeach
</nav>
