<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells every client in a room that its state changed so they re-render from the database.
 * Broadcast synchronously: the game must not depend on a queue worker.
 */
class RoomUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $code;

    public readonly string $status;

    public readonly int $currentRound;

    public function __construct(Room $room, public readonly string $reason)
    {
        $this->code = $room->code;
        $this->status = $room->status->value;
        $this->currentRound = $room->current_round;
    }

    public static function channelName(string $code): string
    {
        return 'room.'.$code;
    }

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel(self::channelName($this->code))];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'code' => $this->code,
            'status' => $this->status,
            'current_round' => $this->currentRound,
            'reason' => $this->reason,
        ];
    }
}
