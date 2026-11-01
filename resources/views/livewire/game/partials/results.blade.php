@php
    $topScore = $leaderboard->first()?->score ?? 0;
    $winners = $leaderboard->where('score', $topScore)->pluck('name');
@endphp

<div class="flex flex-col gap-6">
    <div class="flex flex-col items-center gap-1 text-center">
        <flux:heading size="xl" level="1">{{ __('Final results') }}</flux:heading>

        <flux:text>
            @if ($winners->count() > 1)
                {{ __("It's a tie between :names!", ['names' => $winners->join(', ', ' & ')]) }}
            @else
                {{ __(':name wins!', ['name' => $winners->first()]) }}
            @endif
        </flux:text>
    </div>

    <flux:card>
        <ol class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-700">
            @foreach ($leaderboard as $rank => $member)
                <li wire:key="rank-{{ $member->id }}" class="flex items-center gap-3 py-3">
                    <span class="w-8 text-center text-lg font-semibold">
                        {{ match ($rank) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => $rank + 1 } }}
                    </span>

                    <flux:avatar :name="$member->name" size="sm" />

                    <span class="flex-1 truncate font-medium">
                        {{ $member->name }}
                        @if ($member->is($player))
                            <span class="font-normal text-zinc-500 dark:text-zinc-400">({{ __('you') }})</span>
                        @endif
                    </span>

                    <span class="text-lg font-semibold tabular-nums">{{ $member->score }}</span>
                </li>
            @endforeach
        </ol>
    </flux:card>

    @if ($isAdmin)
        <flux:button wire:click="playAgain" variant="primary" class="w-full" icon="arrow-path" data-test="play-again-button">
            {{ __('Play again') }}
        </flux:button>
    @else
        <flux:text class="text-center text-sm">{{ __('Waiting for :name to start a new game.', ['name' => $room->admin?->name]) }}</flux:text>
    @endif
</div>
