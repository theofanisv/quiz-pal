<?php

namespace App\Livewire\Game;

use App\Actions\Game\AdvanceRound;
use App\Actions\Game\CloseExpiredRound;
use App\Actions\Game\JoinRoom;
use App\Actions\Game\RemovePlayer;
use App\Actions\Game\ReportQuestion;
use App\Actions\Game\ResetRoom;
use App\Actions\Game\SetPlayerReady;
use App\Actions\Game\StartGame;
use App\Actions\Game\SubmitAnswer;
use App\Actions\Game\TrackPresence;
use App\Actions\Game\UpdateRoomSettings;
use App\Enums\QuestionSource;
use App\Events\RoomUpdated;
use App\Exceptions\GameException;
use App\Models\Category;
use App\Models\Player;
use App\Models\Room as RoomModel;
use App\Support\LiveUpdates;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Async;
use Livewire\Component;

/**
 * The single page of a room: join form, lobby, play and results.
 * State lives in the database; every RoomUpdated broadcast re-renders this component.
 */
class Room extends Component
{
    public RoomModel $room;

    public string $name = '';

    public ?int $totalRounds = null;

    public ?int $secondsPerQuestion = null;

    /** @var array<int, int> */
    public array $categories = [];

    public string $questionSource = 'custom';

    /**
     * Questions this visitor reported, so their report button shows as done.
     *
     * @var array<int, int>
     */
    public array $reportedQuestionIds = [];

    public function mount(RoomModel $room): void
    {
        $this->room = $room;

        $this->fillSettings();
    }

    /**
     * Only players subscribe to the room's presence channel.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        if ($this->player() === null) {
            return [];
        }

        $channel = RoomUpdated::channelName($this->room->code);

        return [
            "echo-presence:{$channel},RoomUpdated" => '$refresh',
            "echo-presence:{$channel},here" => 'syncPresence',
            "echo-presence:{$channel},joining" => 'playerJoined',
            "echo-presence:{$channel},leaving" => 'playerLeft',
        ];
    }

    public function join(JoinRoom $joinRoom): void
    {
        $player = $this->guard(fn (): Player => $joinRoom->handle($this->room, $this->name));

        if ($player === null) {
            return;
        }

        Auth::guard('player')->login($player);

        $this->redirectRoute('rooms.show', $this->room);
    }

    public function toggleReady(SetPlayerReady $setPlayerReady): void
    {
        $player = $this->requirePlayer();

        $this->guard(fn () => $setPlayerReady->handle($player, ! $player->is_ready));
    }

    public function updated(string $property): void
    {
        if ($property === 'questionSource') {
            $this->categories = $this->categoryIdsFor($this->questionSource());
        }

        if (in_array(Str::before($property, '.'), ['totalRounds', 'secondsPerQuestion', 'questionSource', 'categories'], true)) {
            $this->saveSettings();
        }
    }

    public function saveSettings(): void
    {
        $player = $this->requirePlayer();

        $this->guard(fn () => app(UpdateRoomSettings::class)->handle($this->room, $player, [
            'total_rounds' => $this->totalRounds,
            'seconds_per_question' => $this->secondsPerQuestion,
            'question_source' => $this->questionSource,
            'categories' => $this->categories,
        ]));
    }

    public function removePlayer(int $playerId, RemovePlayer $removePlayer): void
    {
        $actor = $this->requirePlayer();
        $target = $this->room->players()->findOrFail($playerId);

        $this->guard(fn () => $removePlayer->handle($this->room, $actor, $target));
    }

    public function startGame(StartGame $startGame): void
    {
        $player = $this->requirePlayer();

        $this->guard(fn () => $startGame->handle($this->room, $player));
    }

    /**
     * Record the player's pick and return the choice the server holds for them (null when it
     * rejected the answer), so the pad can keep or release its pick. Async, so the tap does
     * not wait behind a re-render already in flight (answer speed is scored). While the round
     * stays open there is nothing new to render: the pad already shows the pick and
     * PlayerAnswered updates every strip. The answer that reveals the round, and an answer
     * the server rejects, render as usual.
     */
    #[Async]
    public function answer(int $choice, SubmitAnswer $submitAnswer): ?int
    {
        $player = $this->requirePlayer();
        $round = $this->room->currentRound();

        if ($round === null) {
            return null;
        }

        $answer = $this->guard(fn () => $submitAnswer->handle($round, $player, $choice));

        if ($answer === null) {
            return null;
        }

        if (! $round->isRevealed()) {
            $this->skipRender();
        }

        return $answer->choice;
    }

    public function reportQuestion(ReportQuestion $reportQuestion): void
    {
        $player = $this->requirePlayer();
        $round = $this->room->currentRound();

        if ($round === null) {
            return;
        }

        $question = $this->guard(fn () => $reportQuestion->handle($round, $player));

        if ($question === null) {
            return;
        }

        $this->reportedQuestionIds[] = $question->id;

        Flux::toast(variant: 'success', text: __('Thanks! We will check this question.'));
    }

    /**
     * Called by any client whose countdown reached zero; the server clock decides.
     * Returns whether the round is closed. A call that comes too early changes nothing,
     * so it skips the render (a render would restart the clock and call this again);
     * once the round is revealed, by this call or an earlier one, the reveal renders.
     */
    public function timeUp(CloseExpiredRound $closeExpiredRound): bool
    {
        $this->requirePlayer();
        $round = $this->room->currentRound();

        if ($round === null) {
            return true;
        }

        $closeExpiredRound->handle($round);

        if ($round->isRevealed()) {
            return true;
        }

        $this->skipRender();

        return false;
    }

    public function nextRound(AdvanceRound $advanceRound): void
    {
        $player = $this->requirePlayer();

        $this->guard(fn () => $advanceRound->handle($this->room, $player));
    }

    public function playAgain(ResetRoom $resetRoom): void
    {
        $player = $this->requirePlayer();

        $this->guard(fn () => $resetRoom->handle($this->room, $player));
        $this->fillSettings();
    }

    /**
     * @param  array<int, array{id: int|string, name: string}>  $members
     */
    public function syncPresence(array $members, TrackPresence $trackPresence): void
    {
        $this->requirePlayer();

        $trackPresence->sync($this->room, array_map(fn (array $member): int => (int) $member['id'], $members));
    }

    /**
     * @param  array{id: int|string, name: string}  $member
     */
    public function playerJoined(array $member, TrackPresence $trackPresence): void
    {
        $this->requirePlayer();

        $trackPresence->joined($this->room, (int) $member['id']);
    }

    /**
     * @param  array{id: int|string, name: string}  $member
     */
    public function playerLeft(array $member, TrackPresence $trackPresence): void
    {
        $this->requirePlayer();

        $trackPresence->left($this->room, (int) $member['id']);
    }

    public function render(LiveUpdates $liveUpdates): View
    {
        $this->room->refresh();

        $player = $this->player();
        $round = $this->room->isPlaying() ? $this->room->currentRound() : null;
        $answers = $round?->answers()->get()->keyBy('player_id') ?? collect();
        $players = $this->room->players;
        $inLobby = $this->room->isInLobby();

        return view('livewire.game.room', [
            'player' => $player,
            'isAdmin' => $player !== null && $this->room->isAdmin($player),
            'players' => $players,
            'leaderboard' => $players->sortBy([['score', 'desc'], ['name', 'asc']])->values(),
            'round' => $round,
            'answers' => $answers,
            'myAnswer' => $player !== null ? $answers->get($player->id) : null,
            'allCategories' => $inLobby ? Category::query()->forSource($this->room->question_source)->get() : collect(),
            'openTdbAvailable' => $inLobby && Category::query()->forSource(QuestionSource::OpenTdb)->whereHas('questions')->exists(),
            'shareUrl' => route('rooms.show', $this->room),
            'liveUpdatesAvailable' => ! $inLobby || $liveUpdates->isAvailable(),
        ])
            ->title(__('Room :code', ['code' => $this->room->code]))
            ->layout('layouts::game', [
                'description' => __('Your friend invited you to a quiz night. Open the link, pick a name and play.'),
                'robots' => 'noindex, nofollow',
            ]);
    }

    private function fillSettings(): void
    {
        $this->totalRounds = $this->room->total_rounds;
        $this->secondsPerQuestion = $this->room->seconds_per_question;
        $this->questionSource = $this->room->question_source->value;
        $this->categories = $this->room->categories()->pluck('categories.id')->map(fn (int $id): int => $id)->all();
    }

    private function questionSource(): QuestionSource
    {
        return QuestionSource::tryFrom($this->questionSource) ?? QuestionSource::Custom;
    }

    /**
     * Every category of a source, selected whenever the admin switches to it.
     *
     * @return array<int, int>
     */
    private function categoryIdsFor(QuestionSource $source): array
    {
        return Category::query()->forSource($source)->pluck('id')->map(fn (int $id): int => $id)->all();
    }

    /**
     * The current visitor as a player of this room, if they registered.
     */
    private function player(): ?Player
    {
        $player = Auth::guard('player')->user();

        return $player instanceof Player && $player->room_id === $this->room->id ? $player : null;
    }

    private function requirePlayer(): Player
    {
        return $this->player() ?? abort(403);
    }

    /**
     * Run a game action, turning rule violations into a toast and validation
     * failures into field errors on this component.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult|null
     */
    private function guard(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (GameException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError(Str::camel($field), $messages[0]);
            }
        }

        return null;
    }
}
