{{-- The countdown disc. Reads `percent` and `seconds` from the enclosing roundClock Alpine scope. --}}
<div {{ $attributes->class(['flex flex-col items-center gap-1']) }}>
    <div
        class="timer-ring flex size-20 items-center justify-center rounded-full"
        :style="`--p: ${percent}; --ring: ${seconds <= 5 ? 'var(--color-lose)' : 'var(--color-neon-cyan)'}`"
        :class="seconds <= 5 && 'motion-safe:animate-pulse-glow'"
    >
        <div class="flex size-[4.25rem] items-center justify-center rounded-full bg-night-900">
            <span
                class="font-display text-3xl font-bold tabular-nums"
                :class="seconds <= 5 ? 'text-lose' : 'text-ink'"
                x-text="seconds"
                aria-live="off"
            ></span>
        </div>
    </div>

    <span class="text-xs text-ink-muted">{{ __('Time left') }}</span>
</div>
