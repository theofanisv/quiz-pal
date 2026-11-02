<?php

namespace App\Actions\Game;

use App\Actions\Game\Concerns\BroadcastsRoomUpdates;
use App\Exceptions\GameException;
use App\Models\Answer;
use App\Models\Player;
use App\Models\Question;
use App\Models\Round;
use App\Support\Scoring;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

class SubmitAnswer
{
    use BroadcastsRoomUpdates;

    public function __construct(private RevealRound $revealRound) {}

    /**
     * Record a player's choice for the open round. Points depend on correctness and speed.
     * The round is revealed as soon as every connected player has answered.
     *
     * @throws GameException
     */
    public function handle(Round $round, Player $player, int $choice): Answer
    {
        if ($player->room_id !== $round->room_id) {
            throw GameException::notInRoom();
        }

        if ($choice < 0 || $choice >= Question::ANSWER_COUNT) {
            throw new InvalidArgumentException("Choice {$choice} is out of range.");
        }

        $startedAt = $round->started_at;

        if ($startedAt === null || $round->isRevealed()) {
            throw GameException::roundClosed();
        }

        if ($round->isPending()) {
            throw GameException::roundNotStarted();
        }

        if ($round->hasExpired()) {
            $this->revealRound->handle($round);

            throw GameException::roundClosed();
        }

        $answeredAt = now();
        $elapsedMs = (int) round($startedAt->diffInMilliseconds($answeredAt));
        $limitMs = $round->room->seconds_per_question * 1000;
        $isCorrect = $round->question->isCorrect($choice);

        try {
            $answer = $round->answers()->create([
                'player_id' => $player->id,
                'choice' => $choice,
                'is_correct' => $isCorrect,
                'points' => Scoring::points($isCorrect, $elapsedMs, $limitMs),
                'elapsed_ms' => $elapsedMs,
                'answered_at' => $answeredAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $round->answers()->where('player_id', $player->id)->firstOrFail();
        }

        if ($round->everyoneAnswered()) {
            $this->revealRound->handle($round);
        } else {
            $this->announceAnswer($round, $player);
        }

        return $answer;
    }
}
