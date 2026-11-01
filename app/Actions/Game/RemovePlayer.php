<?php

namespace App\Actions\Game;

use App\Actions\Game\Concerns\BroadcastsRoomUpdates;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Room;

class RemovePlayer
{
    use BroadcastsRoomUpdates;

    /**
     * @throws GameException
     */
    public function handle(Room $room, Player $actor, Player $target): void
    {
        if (! $room->isAdmin($actor)) {
            throw GameException::notAdmin();
        }

        if (! $room->isInLobby()) {
            throw GameException::roomNotInLobby();
        }

        if ($target->room_id !== $room->id) {
            throw GameException::notInRoom();
        }

        if ($room->isAdmin($target)) {
            throw GameException::cannotRemoveAdmin();
        }

        $target->delete();

        $this->announce($room, 'player-removed');
    }
}
