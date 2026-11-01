<?php

namespace App\Actions\Game;

use App\Models\Room;

/**
 * Mirror the Reverb presence channel into players.is_connected so that
 * "everyone" only ever means the players who are actually here.
 */
class TrackPresence
{
    public function __construct(private RevealRound $revealRound) {}

    /**
     * @param  array<int, int>  $connectedPlayerIds
     */
    public function sync(Room $room, array $connectedPlayerIds): void
    {
        $room->players()->whereIn('id', $connectedPlayerIds)->update(['is_connected' => true, 'last_seen_at' => now()]);
        $room->players()->whereNotIn('id', $connectedPlayerIds)->update(['is_connected' => false]);

        $this->closeRoundIfEveryoneAnswered($room);
    }

    public function joined(Room $room, int $playerId): void
    {
        $room->players()->whereKey($playerId)->update(['is_connected' => true, 'last_seen_at' => now()]);
    }

    public function left(Room $room, int $playerId): void
    {
        $room->players()->whereKey($playerId)->update(['is_connected' => false]);

        $this->closeRoundIfEveryoneAnswered($room);
    }

    private function closeRoundIfEveryoneAnswered(Room $room): void
    {
        if (! $room->isPlaying()) {
            return;
        }

        $round = $room->currentRound();

        if ($round !== null && $round->isOpen() && $round->everyoneAnswered()) {
            $this->revealRound->handle($round);
        }
    }
}
