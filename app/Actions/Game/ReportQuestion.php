<?php

namespace App\Actions\Game;

use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Question;
use App\Models\Round;

class ReportQuestion
{
    /**
     * Flag the question of a round as possibly wrong, so it waits for review and new games
     * skip it. Reporting again only moves the report time, and a report after a review
     * reopens the question.
     *
     * @throws GameException
     */
    public function handle(Round $round, Player $player): Question
    {
        if ($player->room_id !== $round->game->room_id) {
            throw GameException::notInRoom();
        }

        if (! $round->hasStarted()) {
            throw GameException::roundNotStarted();
        }

        $question = $round->question;
        $question->forceFill(['reported_at' => now()])->save();

        return $question;
    }
}
