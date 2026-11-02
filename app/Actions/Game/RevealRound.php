<?php

namespace App\Actions\Game;

use App\Actions\Game\Concerns\BroadcastsRoomUpdates;
use App\Models\Answer;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Support\Facades\DB;

class RevealRound
{
    use BroadcastsRoomUpdates;

    /**
     * Close the round, note how many players were connected at that moment (the game history
     * compares it with the answers), and add the round points to the players' scores.
     * Safe to call concurrently: only the first caller reveals, the rest get false.
     *
     * The answers are read before the transaction starts on purpose. SQLite opens a
     * deferred transaction with a read snapshot, and once any other request commits
     * (an answer, a session, a presence update) the first write in that transaction
     * fails immediately with "database is locked" instead of waiting for the lock.
     * A transaction whose first statement is a write waits like any other writer.
     */
    public function handle(Round $round): bool
    {
        $revealed = Round::query()
            ->whereKey($round->id)
            ->whereNull('revealed_at')
            ->update([
                'revealed_at' => now()->format(Round::DATE_FORMAT),
                'players_present' => $round->game->room?->connectedPlayers()->count(),
            ]);

        if ($revealed === 0) {
            return false;
        }

        $round->refresh();

        $answers = $round->answers()->where('points', '>', 0)->get();

        DB::transaction(function () use ($answers): void {
            $answers->each(function (Answer $answer): void {
                Player::query()->whereKey($answer->player_id)->increment('score', $answer->points);
            });
        });

        $this->announce($round->game->room, 'revealed');

        return true;
    }
}
