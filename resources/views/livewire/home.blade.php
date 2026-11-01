<div class="flex flex-col gap-8">
    <div class="flex flex-col items-center gap-2 text-center">
        <flux:heading size="xl" level="1">{{ __('Quiz night with friends') }}</flux:heading>
        <flux:text>{{ __('Create a room, share the link or QR code, and play together on your phones.') }}</flux:text>
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg" level="2">{{ __('Create a room') }}</flux:heading>

            <form wire:submit="create" class="flex flex-col gap-4">
                <flux:input
                    wire:model="name"
                    :label="__('Your name')"
                    :placeholder="__('e.g. Maria')"
                    maxlength="20"
                    autocomplete="nickname"
                />

                <flux:button type="submit" variant="primary" class="w-full" icon="plus" data-test="create-room-button">
                    {{ __('Create room') }}
                </flux:button>
            </form>

            <flux:text class="text-sm">{{ __('You become the admin: you choose the categories, the number of rounds and when to start.') }}</flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg" level="2">{{ __('Join a room') }}</flux:heading>

            <form wire:submit="join" class="flex flex-col gap-4">
                <flux:input
                    wire:model="code"
                    :label="__('Room code')"
                    placeholder="ABCD"
                    maxlength="4"
                    autocapitalize="characters"
                    autocomplete="off"
                    class="uppercase tracking-widest"
                />

                <flux:button type="submit" class="w-full" icon="arrow-right-end-on-rectangle" data-test="join-room-button">
                    {{ __('Join room') }}
                </flux:button>
            </form>

            <flux:text class="text-sm">{{ __('Got a link or a QR code from a friend? Just open it.') }}</flux:text>
        </flux:card>
    </div>

    <flux:card class="flex flex-col gap-3">
        <flux:heading size="sm" level="2">{{ __('How it works') }}</flux:heading>
        <ol class="list-inside list-decimal space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
            <li>{{ __('Everyone joins the room and presses ready.') }}</li>
            <li>{{ __('Each round shows a question with four answers. Pick one before the timer runs out.') }}</li>
            <li>{{ __('Correct answers score points, and the faster you answer the more you get.') }}</li>
        </ol>
    </flux:card>
</div>
