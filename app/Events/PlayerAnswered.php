<?php

namespace App\Events;

use App\Models\Player;
use App\Models\Round;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells every client in a room that a player answered the open round. The browsers mark the
 * player in their "who has answered" strip themselves, so an answer costs no server render.
 * Broadcast synchronously: the game must not depend on a queue worker.
 */
class PlayerAnswered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $code;

    public readonly int $roundId;

    public readonly int $playerId;

    public function __construct(Round $round, Player $player)
    {
        $this->code = $round->game->room->code;
        $this->roundId = $round->id;
        $this->playerId = $player->id;
    }

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel(RoomUpdated::channelName($this->code))];
    }

    /**
     * @return array{round_id: int, player_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'round_id' => $this->roundId,
            'player_id' => $this->playerId,
        ];
    }
}
