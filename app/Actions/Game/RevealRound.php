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
     * Close the round and add the round points to the players' scores.
     * Safe to call concurrently: only the first caller reveals, the rest get false.
     */
    public function handle(Round $round): bool
    {
        $revealed = Round::query()
            ->whereKey($round->id)
            ->whereNull('revealed_at')
            ->update(['revealed_at' => now()->format(Round::DATE_FORMAT)]);

        if ($revealed === 0) {
            return false;
        }

        $round->refresh();

        DB::transaction(function () use ($round): void {
            $round->answers()
                ->where('points', '>', 0)
                ->each(function (Answer $answer): void {
                    Player::query()->whereKey($answer->player_id)->increment('score', $answer->points);
                });
        });

        $this->announce($round->room, 'revealed');

        return true;
    }
}
