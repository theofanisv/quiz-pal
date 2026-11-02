{{-- A player's initial on a disc of their room colour. --}}
@props([
    'player',
    'size' => 'sm',
    'ring' => false,
])

@php
    $sizeClasses = match ($size) {
        'xs' => 'size-6 text-[10px]',
        'md' => 'size-10 text-sm',
        'lg' => 'size-14 text-lg',
        'xl' => 'size-20 text-2xl',
        default => 'size-8 text-xs',
    };
@endphp

<span
    data-player-color="{{ $player->color->value }}"
    style="--player-color: {{ $player->color->hex() }}"
    title="{{ $player->name }}"
    {{ $attributes->class([
        'inline-flex shrink-0 items-center justify-center rounded-full bg-(--player-color) font-display font-bold text-night-950 select-none',
        $sizeClasses,
        'glow [--glow:var(--player-color)]' => $ring,
    ]) }}
>{{ Str::upper(Str::substr($player->name, 0, 1)) }}</span>
