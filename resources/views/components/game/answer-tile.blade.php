{{--
    One of the four answers. The tile's neon and shape follow its position on screen;
    `state` is what the server knows: idle, selected (mine, before the reveal),
    correct, wrong (mine) or dim (someone else's wrong answer after the reveal).
--}}
@props([
    'position',
    'choice',
    'text',
    'state' => 'idle',
    'pickedBy' => null,
    'disabled' => false,
])

@php
    $tile = match ($position % 4) {
        0 => 'cyan',
        1 => 'magenta',
        2 => 'lime',
        default => 'amber',
    };
@endphp

<button
    type="button"
    data-state="{{ $state }}"
    data-choice="{{ $choice }}"
    style="--tile: var(--color-neon-{{ $tile }}); animation-delay: {{ $position * 60 }}ms"
    @disabled($disabled)
    {{ $attributes->class([
        'relative flex min-h-16 w-full items-center gap-3 rounded-2xl border-2 bg-night-800 px-4 py-3 text-start text-base font-semibold text-ink transition duration-150 disabled:cursor-default',
        'motion-safe:animate-pop-in' => in_array($state, ['idle', 'selected'], true),
        'border-night-600 hover:border-(--tile) hover:shadow-[0_0_18px_color-mix(in_oklab,var(--tile)_45%,transparent)] active:scale-[0.98]' => $state === 'idle',
        'tile-picked' => $state === 'selected',
        'border-win glow [--glow:var(--color-win)] motion-safe:animate-flip-in' => $state === 'correct',
        'border-lose motion-safe:animate-shake' => $state === 'wrong',
        'border-night-700 opacity-40' => $state === 'dim',
    ]) }}
>
    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-(--tile) text-night-950">
        <x-game.shape :position="$position" class="size-4" />
    </span>

    <span class="flex-1 text-balance">{{ $text }}</span>

    @if ($state === 'correct')
        <flux:icon.check-circle class="size-6 shrink-0 text-win" />
    @elseif ($state === 'wrong')
        <flux:icon.x-circle class="size-6 shrink-0 text-lose" />
    @elseif ($state === 'selected')
        <flux:icon.check class="size-5 shrink-0 text-(--tile)" />
    @endif

    @if ($pickedBy?->isNotEmpty())
        <span class="flex shrink-0 -space-x-1.5">
            @foreach ($pickedBy as $picker)
                <x-game.player-avatar :player="$picker" size="xs" class="ring-2 ring-night-800" />
            @endforeach
        </span>
    @endif
</button>
