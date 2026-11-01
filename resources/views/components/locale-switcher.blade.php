@php
    $current = app()->getLocale();
@endphp

<nav aria-label="{{ __('Language') }}" class="flex items-center gap-1 rounded-full border border-zinc-200 bg-white p-1 text-sm dark:border-zinc-700 dark:bg-zinc-800">
    @foreach (\App\Enums\Locale::cases() as $locale)
        <a
            href="{{ route('locale.switch', $locale) }}"
            @class([
                'rounded-full px-3 py-1 font-medium transition',
                'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $current === $locale->value,
                'text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white' => $current !== $locale->value,
            ])
            @if ($current === $locale->value) aria-current="true" @endif
        >
            {{ $locale->label() }}
        </a>
    @endforeach
</nav>
