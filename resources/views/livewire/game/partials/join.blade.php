<flux:card class="flex flex-col gap-5">
    <div>
        <flux:text>{{ __('Room') }}</flux:text>
        <flux:heading size="xl" level="1" class="tracking-[0.3em]">{{ $room->code }}</flux:heading>
    </div>

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

        <flux:text class="text-sm">
            {{ trans_choice('{0} Nobody is here yet.|{1} :count player is already here.|[2,*] :count players are already here.', $players->count()) }}
        </flux:text>
    @else
        <flux:callout icon="clock" :heading="__('A game is in progress')" :text="__('Wait for this game to end, then reload the page to join the next one.')" />
    @endif
</flux:card>
