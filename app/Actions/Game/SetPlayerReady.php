<?php

namespace App\Actions\Game;

use App\Actions\Game\Concerns\BroadcastsRoomUpdates;
use App\Exceptions\GameException;
use App\Models\Player;

class SetPlayerReady
{
    use BroadcastsRoomUpdates;

    /**
     * @throws GameException
     */
    public function handle(Player $player, bool $ready): Player
    {
        $room = $player->room;

        if (! $room->isInLobby()) {
            throw GameException::roomNotInLobby();
        }

        $player->update(['is_ready' => $ready]);

        $this->announce($room, 'ready-changed');

        return $player;
    }
}
