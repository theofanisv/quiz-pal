{{-- A player's card in the lobby roster: avatar, name, admin crown and whatever badges the slot adds. --}}
@props([
    'player',
    'you' => false,
    'admin' => false,
    'muted' => false,
])

<div
    style="--player-color: {{ $player->color->hex() }}"
    {{ $attributes->class([
        'relative flex flex-col items-center gap-2 rounded-2xl border border-night-700 bg-night-900/60 p-3 text-center transition',
        'opacity-50' => $muted,
    ]) }}
>
    <x-game.player-avatar :player="$player" size="lg" />

    <span class="flex max-w-full items-center gap-1">
        <span class="truncate font-semibold">{{ $player->name }}</span>

        @if ($admin)
            <flux:icon.crown class="size-4 shrink-0 text-neon-amber" aria-label="{{ __('Admin') }}" />
        @endif
    </span>

    @if ($you || $slot->isNotEmpty())
        <span class="flex flex-wrap items-center justify-center gap-1">
            @if ($you)
                <flux:badge size="sm">{{ __('You') }}</flux:badge>
            @endif

            {{ $slot }}
        </span>
    @endif
</div>
