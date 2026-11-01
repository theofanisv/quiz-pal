<?php

namespace App\Actions\Game;

use App\Models\Room;
use App\Models\Round;

class StartRound
{
    /**
     * Open the given round for answers and start its timer.
     */
    public function handle(Room $room, int $roundNumber): Round
    {
        $round = $room->rounds()->where('round_number', $roundNumber)->firstOrFail();

        $startedAt = now();

        $round->update([
            'started_at' => $startedAt,
            'ends_at' => $startedAt->addSeconds($room->seconds_per_question),
        ]);

        $room->update(['current_round' => $roundNumber]);

        return $round;
    }
}
