<?php

namespace App\Actions\Game;

use App\Actions\Game\Concerns\BroadcastsRoomUpdates;
use App\Enums\RoomStatus;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class ResetRoom
{
    use BroadcastsRoomUpdates;

    /**
     * Send a finished room back to the lobby with the same players and settings.
     * The finished game, its rounds and answers stay as history.
     *
     * @throws GameException
     */
    public function handle(Room $room, Player $actor): Room
    {
        if (! $room->isAdmin($actor)) {
            throw GameException::notAdmin();
        }

        if (! $room->isFinished()) {
            throw GameException::notFinished();
        }

        DB::transaction(function () use ($room): void {
            $room->players()->update(['score' => 0, 'is_ready' => false]);
            $room->update(['status' => RoomStatus::Lobby, 'current_round' => 0]);
        });

        $this->announce($room, 'reset');

        return $room;
    }
}
