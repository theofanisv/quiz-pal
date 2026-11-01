<?php

namespace App\Actions\Game;

use App\Models\Room;
use App\Models\Round;

class StartRound
{
    /**
     * Schedule the round: the question is shown to everyone a few seconds from now, so every
     * device has received the broadcast by then, and the timer runs from that same instant.
     */
    public function handle(Room $room, int $roundNumber): Round
    {
        $round = $room->rounds()->where('round_number', $roundNumber)->firstOrFail();

        $startsAt = now()->addSeconds(Round::START_DELAY_SECONDS);

        $round->update([
            'started_at' => $startsAt,
            'ends_at' => $startsAt->addSeconds($room->seconds_per_question),
        ]);

        $room->update(['current_round' => $roundNumber]);

        return $round;
    }
}
