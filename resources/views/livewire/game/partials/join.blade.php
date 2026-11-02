<flux:card class="flex flex-col gap-5 rounded-3xl" x-data="gameScreen('join')">
    <x-game.room-code :code="$room->code" />

    @if ($room->isInLobby())
        <form wire:submit="join" class="flex flex-col gap-4">
            <flux:input
                wire:model="name"
                :label="__('Your name')"
                :placeholder="__('e.g. Nikos')"
                maxlength="20"
                autofocus
                autocomplete="nickname"
            />

            <flux:button type="submit" variant="primary" class="w-full" data-test="join-button">
                {{ __('Join room') }}
            </flux:button>
        </form>

        <div class="flex items-center gap-3">
            @if ($players->isNotEmpty())
                <span class="flex shrink-0 -space-x-2">
                    @foreach ($players->take(8) as $member)
                        <x-game.player-avatar :player="$member" size="sm" class="ring-2 ring-night-800" />
                    @endforeach
                </span>
            @endif

            <flux:text class="text-sm">
                {{ trans_choice('{0} Nobody is here yet.|{1} :count player is already here.|[2,*] :count players are already here.', $players->count()) }}
            </flux:text>
        </div>
    @else
        <flux:callout icon="clock" :heading="__('A game is in progress')" :text="__('Wait for this game to end, then reload the page to join the next one.')" />
    @endif
</flux:card>
