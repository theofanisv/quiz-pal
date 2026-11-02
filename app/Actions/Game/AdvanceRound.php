<?php

namespace App\Actions\Game;

use App\Actions\Game\Concerns\BroadcastsRoomUpdates;
use App\Enums\RoomStatus;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Room;

class AdvanceRound
{
    use BroadcastsRoomUpdates;

    public function __construct(private StartRound $startRound) {}

    /**
     * Move on from a revealed round: open the next one, or finish the room and its game after the last.
     *
     * @throws GameException
     */
    public function handle(Room $room, Player $actor): Room
    {
        if (! $room->isAdmin($actor)) {
            throw GameException::notAdmin();
        }

        if (! $room->isPlaying()) {
            throw GameException::notPlaying();
        }

        $round = $room->currentRound();

        if ($round === null || ! $round->isRevealed()) {
            throw GameException::roundStillOpen();
        }

        if ($room->isLastRound()) {
            $room->update(['status' => RoomStatus::Finished]);
            $room->currentGame->update(['finished_at' => now()]);

            $this->announce($room, 'finished');

            return $room;
        }

        $this->startRound->handle($room, $room->current_round + 1);

        $this->announce($room, 'next-round');

        return $room;
    }
}
