<div class="flex flex-col gap-6" @if ($player !== null) wire:poll.10s @endif>
    @if ($player === null)
        @include('livewire.game.partials.join')
    @elseif ($room->isInLobby())
        @include('livewire.game.partials.lobby')
    @elseif ($round !== null)
        @include('livewire.game.partials.play')
    @else
        @include('livewire.game.partials.results')
    @endif
</div>
