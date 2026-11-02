{{-- The room's four-letter code, large enough to read across a table. --}}
@props(['code'])

<div {{ $attributes->class(['flex flex-col gap-1']) }}>
    <span class="text-sm text-ink-muted">{{ __('Room code') }}</span>
    <span class="font-display text-5xl font-bold tracking-[0.3em] text-neon-cyan glow-text">{{ $code }}</span>
</div>
