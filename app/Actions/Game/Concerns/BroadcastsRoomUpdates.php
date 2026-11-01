<?php

namespace App\Actions\Game\Concerns;

use App\Events\RoomUpdated;
use App\Models\Room;
use Throwable;

trait BroadcastsRoomUpdates
{
    /**
     * Tell every other client in the room to re-render.
     *
     * A failed broadcast is reported but never fails the action: the database change
     * already happened and every page polls as a fallback. Starting a game is the one
     * place that insists on a working Reverb server (see StartGame).
     */
    protected function announce(Room $room, string $reason): void
    {
        try {
            $pending = broadcast(new RoomUpdated($room->fresh() ?? $room, $reason))->toOthers();

            unset($pending);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
