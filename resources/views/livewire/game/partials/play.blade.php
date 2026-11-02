@php
    $question = $round->question;
    $revealed = $round->isRevealed();
    $answersByChoice = $answers->groupBy('choice');
    $strip = $revealed ? $leaderboard : $players;
    $canAnswer = ! $revealed && $myAnswer === null;
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
    <flux:card x-show="!started" class="flex flex-col items-center gap-3 rounded-3xl py-10 text-center">
        <h2 class="font-display text-xl font-bold text-ink-muted">{{ __('Get ready…') }}</h2>
        <span class="font-display text-8xl font-extrabold tabular-nums text-neon-cyan glow-text motion-safe:animate-pulse-glow" x-text="startsIn" aria-live="polite"></span>
    </flux:card>

    <div x-show="started" x-cloak class="flex flex-col gap-5">
        <flux:card class="flex items-start gap-4 rounded-3xl">
            <h2 class="flex-1 font-display text-2xl font-bold text-balance sm:text-3xl">{{ $question->text }}</h2>

            @unless ($revealed)
                <x-game.timer-ring class="shrink-0" />
            @endunless
        </flux:card>

        {{--
            The pad highlights the tapped tile at once and locks the others while the server
            call is in flight; the server's answer (selected / correct / wrong / dim) replaces
            it on the next render, which also re-initialises this pad from the server. Alpine
            toggles `tile-picking` (never `tile-picked`) so its clean-up cannot strip the
            server's class. A rejected answer leaves the tile idle, so the pad unlocks.
        --}}
        <div class="grid gap-3 sm:grid-cols-2" x-data="answerPad({ open: @js($canAnswer) })">
            @foreach ($round->displayedAnswers() as $index => $text)
                @php
                    $isCorrect = $question->isCorrect($index);
                    $isMine = $myAnswer?->choice === $index;
                    $state = match (true) {
                        $revealed && $isCorrect => 'correct',
                        $revealed && $isMine => 'wrong',
                        $revealed => 'dim',
                        $isMine => 'selected',
                        default => 'idle',
                    };
                    $pickedBy = $revealed
                        ? $answersByChoice->get($index, collect())
                            ->map(fn ($answer) => $players->firstWhere('id', $answer->player_id))
                            ->filter()
                            ->values()
                        : null;
                @endphp

                <x-game.answer-tile
                    wire:key="answer-{{ $round->id }}-{{ $index }}"
                    :position="$loop->index"
                    :choice="$index"
                    :text="$text"
                    :state="$state"
                    :picked-by="$pickedBy"
                    :disabled="! $canAnswer"
                    x-on:click="pick({{ $index }})"
                    x-bind:class="picked === {{ $index }} && 'tile-picking'"
                    x-bind:disabled="! open || picked !== null"
                />
            @endforeach
        </div>

        <flux:card class="flex flex-col gap-3 rounded-3xl">
            <h3 class="font-display text-base font-bold">{{ $revealed ? __('Scores') : __('Who has answered') }}</h3>

            @if ($revealed)
                <ol class="flex flex-col gap-2">
                    @foreach ($strip as $rank => $member)
                        @php
                            $memberAnswer = $answers->get($member->id);
                        @endphp

                        <li wire:key="strip-{{ $member->id }}" class="flex items-center gap-3">
                            <span class="w-5 text-center font-display text-sm font-bold text-ink-muted">{{ $rank + 1 }}</span>
                            <x-game.player-avatar :player="$member" size="xs" />

                            <span class="flex-1 truncate">
                                {{ $member->name }}
                                @if ($member->is($player))
                                    <span class="text-ink-muted">({{ __('you') }})</span>
                                @endif
                            </span>

                            @if ($memberAnswer?->elapsed_ms !== null)
                                <span class="text-xs text-ink-muted tabular-nums">{{ __(':seconds s', ['seconds' => number_format($memberAnswer->elapsed_ms / 1000, 1)]) }}</span>
                            @endif

                            @if ($memberAnswer?->points)
                                <span class="font-display text-sm font-bold text-win motion-safe:animate-score-pop">+{{ $memberAnswer->points }}</span>
                            @endif

                            <span class="w-14 text-end font-display font-bold tabular-nums">{{ $member->score }}</span>
                        </li>
                    @endforeach
                </ol>
            @else
                <ul class="flex flex-wrap gap-3">
                    @foreach ($strip as $member)
                        @php
                            $hasAnswered = $answers->has($member->id);
                        @endphp

                        <li wire:key="strip-{{ $member->id }}" class="flex w-16 flex-col items-center gap-1 text-center">
                            <span class="relative">
                                <x-game.player-avatar
                                    :player="$member"
                                    size="md"
                                    :class="$hasAnswered ? 'glow [--glow:var(--player-color)]' : 'opacity-40'"
                                />

                                @if ($hasAnswered)
                                    <span class="absolute -right-1 -bottom-1 flex size-5 items-center justify-center rounded-full bg-win text-night-950 ring-2 ring-night-800">
                                        <flux:icon.check class="size-3" variant="micro" />
                                    </span>
                                @endif
                            </span>

                            <span class="max-w-full truncate text-xs font-medium">{{ $member->name }}</span>

                            @if ($hasAnswered)
                                <span class="text-[10px] font-semibold text-win">{{ __('Answered') }}</span>
                            @elseif (! $member->is_connected)
                                <span class="text-[10px] text-ink-muted">{{ __('Away') }}</span>
                            @else
                                <span class="text-[10px] text-ink-muted">{{ __('Thinking…') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
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

        @if ($room->question_source === \App\Enums\QuestionSource::OpenTdb)
            <flux:text class="text-center text-xs">{{ __('Questions from the Open Trivia Database (CC BY-SA 4.0).') }}</flux:text>
        @endif
    </div>
</div>
