@php
    $topScore = $leaderboard->first()?->score ?? 0;
    $winners = $leaderboard->where('score', $topScore)->pluck('name');
@endphp

<div
    wire:key="results-{{ $room->id }}"
    x-data="resultsScreen({ winner: @js($winners->contains($player->name)) })"
    class="flex flex-col gap-6"
>
    <canvas x-ref="confetti" aria-hidden="true" class="pointer-events-none fixed inset-0 z-40 size-full"></canvas>

    <div class="flex flex-col items-center gap-1 text-center">
        <h1 class="font-display text-3xl font-extrabold">{{ __('Final results') }}</h1>

        <flux:text>
            @if ($winners->count() > 1)
                {{ __("It's a tie between :names!", ['names' => $winners->join(', ', ' & ')]) }}
            @else
                {{ __(':name wins!', ['name' => $winners->first()]) }}
            @endif
        </flux:text>
    </div>

    <x-game.podium :leaderboard="$leaderboard" :player="$player" :correct-answers="$correctAnswers" :total-rounds="$room->total_rounds" />

    @if ($isAdmin)
        <flux:button wire:click="playAgain" x-on:click="$store.sound.play('click')" variant="primary" class="w-full" icon="arrow-path" data-test="play-again-button">
            {{ __('Play again') }}
        </flux:button>
    @else
        <flux:text class="text-center text-sm">{{ __('Waiting for :name to start a new game.', ['name' => $room->admin?->name]) }}</flux:text>
    @endif
</div>
