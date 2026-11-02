<div class="flex flex-col gap-8">
    <div class="relative flex flex-col items-center gap-3 py-6 text-center">
        {{-- The four answer shapes drift behind the title, the game's own vocabulary as decoration. --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
            <x-game.shape :position="0" class="absolute top-0 left-[6%] size-8 text-neon-cyan/50 motion-safe:animate-float" />
            <x-game.shape :position="1" class="absolute top-4 right-[8%] size-7 text-neon-magenta/50 motion-safe:animate-float [animation-delay:-2s]" />
            <x-game.shape :position="2" class="absolute top-24 left-[2%] size-5 text-neon-lime/40 motion-safe:animate-float [animation-delay:-4s]" />
            <x-game.shape :position="3" class="absolute top-28 right-[3%] size-6 text-neon-amber/40 motion-safe:animate-float [animation-delay:-5.5s]" />
        </div>

        <h1 class="font-display text-4xl font-extrabold tracking-tight text-balance sm:text-5xl">
            <span class="bg-linear-to-r from-neon-cyan via-neon-magenta to-neon-amber bg-clip-text text-transparent">{{ __('Quiz night with friends') }}</span>
        </h1>

        <p class="max-w-md text-ink-muted">{{ __('Create a room, share the link or QR code, and play together on your phones.') }}</p>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <flux:card class="flex flex-col gap-4 rounded-3xl">
            <h2 class="font-display text-xl font-bold">{{ __('Create a room') }}</h2>

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

        <flux:card class="flex flex-col gap-4 rounded-3xl">
            <h2 class="font-display text-xl font-bold">{{ __('Join a room') }}</h2>

            <form wire:submit="join" class="flex flex-col gap-4">
                <flux:input
                    wire:model="code"
                    :label="__('Room code')"
                    placeholder="ABCD"
                    maxlength="4"
                    autocapitalize="characters"
                    autocomplete="off"
                    class="text-center font-display text-2xl font-bold tracking-[0.4em] uppercase"
                />

                <flux:button type="submit" variant="filled" class="w-full" icon="arrow-right-end-on-rectangle" data-test="join-room-button">
                    {{ __('Join room') }}
                </flux:button>
            </form>

            <flux:text class="text-sm">{{ __('Got a link or a QR code from a friend? Just open it.') }}</flux:text>
        </flux:card>
    </div>

    <flux:card class="flex flex-col gap-4 rounded-3xl">
        <h2 class="font-display text-lg font-bold">{{ __('How it works') }}</h2>

        <ol class="flex flex-col gap-3 text-sm text-ink-muted">
            @foreach ([
                __('Everyone joins the room and presses ready.'),
                __('Each round shows a question with four answers. Pick one before the timer runs out.'),
                __('Correct answers score points, and the faster you answer the more you get.'),
            ] as $step)
                <li class="flex items-start gap-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full border border-neon-cyan/60 font-display text-sm font-bold text-neon-cyan">{{ $loop->iteration }}</span>
                    <span class="pt-1">{{ $step }}</span>
                </li>
            @endforeach
        </ol>
    </flux:card>
</div>
