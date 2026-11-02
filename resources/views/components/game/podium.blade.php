{{--
    The final standings: the top three on a podium (first in the middle, drawn tallest),
    everyone else in rows below, each with how many questions they answered correctly.
    The DOM keeps rank order; CSS `order` arranges the columns.
--}}
@props(['leaderboard', 'player', 'correctAnswers', 'totalRounds'])

@php
    $top = $leaderboard->take(3)->values();
    $rest = $leaderboard->slice(3);
    $columns = [
        0 => ['order' => 'order-2', 'height' => 'h-28', 'size' => 'xl', 'delay' => 300],
        1 => ['order' => 'order-1', 'height' => 'h-20', 'size' => 'lg', 'delay' => 150],
        2 => ['order' => 'order-3', 'height' => 'h-14', 'size' => 'lg', 'delay' => 0],
    ];
@endphp

<div class="flex flex-col gap-6">
    <ol class="flex items-end justify-center gap-3">
        @foreach ($top as $rank => $member)
            @php $column = $columns[$rank]; @endphp

            <li
                wire:key="podium-{{ $member->id }}"
                style="--player-color: {{ $member->color->hex() }}"
                class="{{ $column['order'] }} flex w-28 flex-col items-center gap-2"
            >
                @if ($rank === 0)
                    <flux:icon.crown class="size-6 text-neon-amber motion-safe:animate-float" aria-hidden="true" />
                @endif

                <x-game.player-avatar :player="$member" :size="$column['size']" ring />

                <span class="max-w-full truncate text-sm font-semibold">
                    {{ $member->name }}
                    @if ($member->is($player))
                        <span class="font-normal text-ink-muted">({{ __('you') }})</span>
                    @endif
                </span>

                <div class="flex flex-col items-center">
                    <span class="font-display text-xl font-bold tabular-nums">{{ $member->score }}</span>
                    <span class="text-xs text-ink-muted tabular-nums">{{ __(':correct/:total correct', ['correct' => $correctAnswers[$member->id] ?? 0, 'total' => $totalRounds]) }}</span>
                </div>

                <div
                    class="{{ $column['height'] }} flex w-full origin-bottom items-start justify-center rounded-t-2xl bg-(--player-color) pt-2 font-display text-2xl font-bold text-night-950 motion-safe:animate-rise"
                    style="animation-delay: {{ $column['delay'] }}ms"
                >
                    {{ $rank + 1 }}
                </div>
            </li>
        @endforeach
    </ol>

    @if ($rest->isNotEmpty())
        <ol class="flex flex-col divide-y divide-night-700 rounded-2xl border border-night-700 bg-night-900/60 px-4" start="4">
            @foreach ($rest as $rank => $member)
                <li wire:key="rank-{{ $member->id }}" class="flex items-center gap-3 py-3">
                    <span class="w-6 text-center font-display text-lg font-bold text-ink-muted">{{ $rank + 1 }}</span>
                    <x-game.player-avatar :player="$member" size="sm" />
                    <span class="flex-1 truncate font-medium">
                        {{ $member->name }}
                        @if ($member->is($player))
                            <span class="font-normal text-ink-muted">({{ __('you') }})</span>
                        @endif
                    </span>
                    <span class="text-xs text-ink-muted tabular-nums">{{ __(':correct/:total correct', ['correct' => $correctAnswers[$member->id] ?? 0, 'total' => $totalRounds]) }}</span>
                    <span class="font-display text-lg font-bold tabular-nums">{{ $member->score }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</div>
