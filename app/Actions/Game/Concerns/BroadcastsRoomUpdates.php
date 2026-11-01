<?php

namespace App\Actions\Game\Concerns;

use App\Events\RoomUpdated;
use App\Models\Room;

trait BroadcastsRoomUpdates
{
    /**
     * Tell every other client in the room to re-render.
     */
    protected function announce(Room $room, string $reason): void
    {
        broadcast(new RoomUpdated($room->fresh() ?? $room, $reason))->toOthers();
    }
}
