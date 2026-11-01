@php
    $question = $round->question;
    $revealed = $round->isRevealed();
    $answersByChoice = $answers->groupBy('choice');
    $strip = $revealed ? $leaderboard : $players;
@endphp

<div
    wire:key="round-{{ $round->id }}"
    x-data="roundClock({ startsAt: {{ $round->started_at?->getTimestampMs() ?? 0 }}, endsAt: {{ $round->ends_at?->getTimestampMs() ?? 0 }}, serverNow: {{ now()->getTimestampMs() }}, totalMs: {{ $room->seconds_per_question * 1000 }} })"
    class="flex flex-col gap-5"
>
    <div class="flex items-center justify-between gap-3">
        <flux:badge>{{ __('Round :current of :total', ['current' => $room->current_round, 'total' => $room->total_rounds]) }}</flux:badge>
        <flux:badge color="zinc">{{ $question->category->name }}</flux:badge>
    </div>

    {{-- Shown until the server start moment, on every device at once. --}}
    <flux:card x-show="!started" class="flex flex-col items-center gap-2 py-10 text-center">
        <flux:heading size="lg" level="2">{{ __('Get ready…') }}</flux:heading>
        <span class="font-mono text-6xl font-semibold tabular-nums" x-text="startsIn" aria-live="polite"></span>
    </flux:card>

    <div x-show="started" x-cloak class="flex flex-col gap-5">
        @unless ($revealed)
            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-400">
                    <span>{{ __('Time left') }}</span>
                    <span class="font-mono text-lg font-semibold tabular-nums text-zinc-900 dark:text-white" x-text="seconds"></span>
                </div>

                <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                    <div class="h-full rounded-full bg-accent transition-[width] duration-100 ease-linear" :style="`width: ${percent}%`"></div>
                </div>
            </div>
        @endunless

        <flux:card>
            <flux:heading size="xl" level="2" class="text-balance">{{ $question->text }}</flux:heading>
        </flux:card>

        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($round->displayedAnswers() as $index => $text)
                @php
                    $isCorrect = $question->isCorrect($index);
                    $isMine = $myAnswer?->choice === $index;
                    $pickedBy = $answersByChoice->get($index, collect())
                        ->map(fn ($answer) => $players->firstWhere('id', $answer->player_id)?->name)
                        ->filter();
                @endphp

                <button
                    type="button"
                    wire:key="answer-{{ $round->id }}-{{ $index }}"
                    wire:click="answer({{ $index }})"
                    wire:loading.attr="disabled"
                    @disabled($revealed || $myAnswer !== null)
                    @class([
                        'flex min-h-16 flex-col items-start gap-1 rounded-xl border-2 px-4 py-3 text-start text-base font-medium transition disabled:cursor-default',
                        'border-zinc-200 bg-white hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-500' => ! $revealed && ! $isMine,
                        'border-accent bg-accent/10 dark:bg-accent/20' => ! $revealed && $isMine,
                        'border-green-500 bg-green-50 dark:bg-green-950' => $revealed && $isCorrect,
                        'border-red-400 bg-red-50 dark:bg-red-950' => $revealed && ! $isCorrect && $isMine,
                        'border-zinc-200 bg-zinc-100 opacity-70 dark:border-zinc-700 dark:bg-zinc-800' => $revealed && ! $isCorrect && ! $isMine,
                    ])
                >
                    <span class="flex w-full items-center gap-2">
                        <span class="flex-1">{{ $text }}</span>

                        @if ($revealed && $isCorrect)
                            <flux:icon.check-circle class="size-5 shrink-0 text-green-600" />
                        @elseif (! $revealed && $isMine)
                            <flux:icon.check class="size-5 shrink-0" />
                        @endif
                    </span>

                    @if ($revealed && $pickedBy->isNotEmpty())
                        <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ $pickedBy->join(', ') }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        <flux:card class="flex flex-col gap-3">
            <flux:heading size="sm" level="3">{{ $revealed ? __('Scores') : __('Who has answered') }}</flux:heading>

            <ul class="flex flex-col gap-2">
                @foreach ($strip as $member)
                    @php
                        $memberAnswer = $answers->get($member->id);
                    @endphp

                    <li wire:key="strip-{{ $member->id }}" class="flex items-center gap-3">
                        <flux:avatar :name="$member->name" size="xs" />

                        <span class="flex-1 truncate">
                            {{ $member->name }}
                            @if ($member->is($player))
                                <span class="text-zinc-500 dark:text-zinc-400">({{ __('you') }})</span>
                            @endif
                        </span>

                        @if ($revealed)
                            @if ($memberAnswer?->points)
                                <span class="text-sm font-medium text-green-600">+{{ $memberAnswer->points }}</span>
                            @endif
                            <span class="w-12 text-end font-semibold tabular-nums">{{ $member->score }}</span>
                        @elseif ($memberAnswer !== null)
                            <flux:badge color="green" size="sm" icon="check">{{ __('Answered') }}</flux:badge>
                        @elseif (! $member->is_connected)
                            <flux:badge color="zinc" size="sm">{{ __('Away') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('Thinking…') }}</flux:badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        </flux:card>

        @if ($revealed)
            @if ($isAdmin)
                <flux:button wire:click="nextRound" variant="primary" class="w-full" icon-trailing="arrow-right" data-test="next-button">
                    {{ $room->isLastRound() ? __('Show final results') : __('Next question') }}
                </flux:button>
            @else
                <flux:text class="text-center text-sm">{{ __('Waiting for :name to continue.', ['name' => $room->admin?->name]) }}</flux:text>
            @endif
        @endif
    </div>
</div>
