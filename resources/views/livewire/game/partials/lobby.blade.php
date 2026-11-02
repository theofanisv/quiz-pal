@php
    $everyoneReady = $room->everyoneReady();
@endphp

<div class="flex flex-col gap-6">
    <flux:card class="flex flex-col gap-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:text>{{ __('Room code') }}</flux:text>
                <flux:heading size="xl" level="1" class="tracking-[0.3em]">{{ $room->code }}</flux:heading>
            </div>

            <x-qr-code :url="$shareUrl" :size="112" class="shrink-0" />
        </div>

        <flux:input :value="$shareUrl" readonly copyable :label="__('Share this link')" />

        <flux:text class="text-sm">{{ __('Friends can scan the code or open the link to join.') }}</flux:text>
    </flux:card>

    <flux:card class="flex flex-col gap-3">
        <flux:heading size="lg" level="2">{{ __('Players') }} ({{ $players->count() }})</flux:heading>

        <ul class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-700">
            @foreach ($players as $member)
                <li wire:key="player-{{ $member->id }}" class="flex items-center gap-3 py-2">
                    <flux:avatar :name="$member->name" size="sm" />

                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <span class="truncate font-medium">{{ $member->name }}</span>

                        @if ($room->isAdmin($member))
                            <flux:icon.crown class="size-4 shrink-0 text-amber-500" aria-label="{{ __('Admin') }}" />
                        @endif

                        @if ($member->is($player))
                            <flux:badge size="sm">{{ __('You') }}</flux:badge>
                        @endif

                        @unless ($member->is_connected)
                            <flux:badge size="sm" color="zinc">{{ __('Away') }}</flux:badge>
                        @endunless
                    </div>

                    @if ($member->is_ready)
                        <flux:badge color="green" size="sm" icon="check">{{ __('Ready') }}</flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">{{ __('Not ready') }}</flux:badge>
                    @endif

                    @if ($isAdmin && ! $room->isAdmin($member))
                        <flux:button
                            size="xs"
                            variant="ghost"
                            icon="x-mark"
                            wire:click="removePlayer({{ $member->id }})"
                            :aria-label="__('Remove :name', ['name' => $member->name])"
                        />
                    @endif
                </li>
            @endforeach
        </ul>
    </flux:card>

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg" level="2">{{ __('Game settings') }}</flux:heading>

        @if ($isAdmin)
            <div class="grid grid-cols-2 gap-4">
                <flux:input
                    type="number"
                    wire:model.live.number.debounce.500ms="totalRounds"
                    :label="__('Rounds')"
                    :min="\App\Models\Room::MIN_ROUNDS"
                    :max="\App\Models\Room::MAX_ROUNDS"
                />

                <flux:input
                    type="number"
                    wire:model.live.number.debounce.500ms="secondsPerQuestion"
                    :label="__('Seconds per question')"
                    :min="\App\Models\Room::MIN_SECONDS"
                    :max="\App\Models\Room::MAX_SECONDS"
                />
            </div>

            <flux:radio.group wire:model.live="questionSource" :label="__('Question source')">
                @foreach (\App\Enums\QuestionSource::cases() as $source)
                    @php
                        $synced = $source !== \App\Enums\QuestionSource::OpenTdb || $openTdbAvailable;
                    @endphp

                    <flux:radio
                        wire:key="source-{{ $source->value }}"
                        :value="$source->value"
                        :label="$source->label()"
                        :description="$synced ? $source->description() : __('Not available on this server yet.')"
                        :disabled="! $synced"
                    />
                @endforeach
            </flux:radio.group>

            <flux:checkbox.group wire:model.live="categories" :label="__('Categories')">
                @foreach ($allCategories as $category)
                    <flux:checkbox wire:key="category-{{ $category->id }}" :value="$category->id" :label="$category->name" />
                @endforeach
            </flux:checkbox.group>
        @else
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Rounds') }}</dt>
                <dd class="font-medium">{{ $room->total_rounds }}</dd>

                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Seconds per question') }}</dt>
                <dd class="font-medium">{{ $room->seconds_per_question }}</dd>

                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Question source') }}</dt>
                <dd class="font-medium">{{ $room->question_source->label() }}</dd>

                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Categories') }}</dt>
                <dd class="font-medium">{{ $room->categories->pluck('name')->join(', ') }}</dd>
            </dl>
        @endif
    </flux:card>

    @unless ($liveUpdatesAvailable)
        <flux:callout
            variant="danger"
            icon="exclamation-triangle"
            :heading="__('Live updates are unavailable')"
            :text="__('The game cannot start until the live update server (Reverb) is back. Players can still join and get ready.')"
        />
    @endunless

    <div class="flex flex-col gap-3">
        <flux:button
            wire:click="toggleReady"
            :variant="$player->is_ready ? 'filled' : 'primary'"
            :icon="$player->is_ready ? 'check' : null"
            class="w-full"
            data-test="ready-button"
        >
            {{ $player->is_ready ? __('Ready! Tap to undo') : __("I'm ready") }}
        </flux:button>

        @if ($isAdmin)
            <flux:button
                wire:click="startGame"
                variant="primary"
                color="green"
                icon="play"
                class="w-full"
                :disabled="! $everyoneReady || ! $liveUpdatesAvailable"
                data-test="start-button"
            >
                {{ __('Start game') }}
            </flux:button>

            @unless ($everyoneReady)
                <flux:text class="text-center text-sm">{{ __('The game can start when everyone is ready.') }}</flux:text>
            @endunless
        @else
            <flux:text class="text-center text-sm">{{ __('Waiting for :name to start the game.', ['name' => $room->admin?->name]) }}</flux:text>
        @endif
    </div>
</div>
