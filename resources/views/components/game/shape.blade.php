{{-- The four answer shapes, by tile position: triangle, diamond, circle, square. --}}
@props(['position'])

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" {{ $attributes }}>
    @switch($position % 4)
        @case(0)
            <path d="M12 3l10 17H2z" />
            @break
        @case(1)
            <path d="M12 2l10 10-10 10L2 12z" />
            @break
        @case(2)
            <circle cx="12" cy="12" r="10" />
            @break
        @default
            <rect x="3" y="3" width="18" height="18" rx="3" />
    @endswitch
</svg>
