{{--
    The QuizPal mark: a navy tile holding the four answer shapes in their neons, with an
    optional wordmark. The flat version of the mark lives in public/favicon.svg.
--}}
@props([
    'wordmark' => true,
])

<span {{ $attributes->class(['inline-flex items-center gap-2']) }}>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" class="size-8 shrink-0" aria-hidden="true">
        <defs>
            <filter id="quizpal-logo-glow" x="-50%" y="-50%" width="200%" height="200%">
                <feGaussianBlur stdDeviation="2.5" />
            </filter>
        </defs>

        <rect x="1" y="1" width="62" height="62" rx="14" fill="#0b1020" stroke="#2a3766" stroke-width="1" />

        <g filter="url(#quizpal-logo-glow)" opacity="0.85">
            <path d="M22 14.5 30 28.5H14Z" fill="#22e1ff" />
            <path d="M42 14 50 22 42 30 34 22Z" fill="#ff3cac" />
            <circle cx="22" cy="42" r="8" fill="#b4ff39" />
            <rect x="34.5" y="34.5" width="15" height="15" rx="3" fill="#ffb830" />
        </g>

        <path d="M22 14.5 30 28.5H14Z" fill="#22e1ff" />
        <path d="M42 14 50 22 42 30 34 22Z" fill="#ff3cac" />
        <circle cx="22" cy="42" r="8" fill="#b4ff39" />
        <rect x="34.5" y="34.5" width="15" height="15" rx="3" fill="#ffb830" />
    </svg>

    @if ($wordmark)
        <span class="font-display text-xl font-extrabold tracking-tight">Quiz<span class="text-neon-cyan">Pal</span></span>
    @else
        <span class="sr-only">{{ config('app.name') }}</span>
    @endif
</span>
