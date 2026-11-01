<?php

namespace App\Actions\Game;

use App\Actions\Game\Concerns\BroadcastsRoomUpdates;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JoinRoom
{
    use BroadcastsRoomUpdates;

    /**
     * Register a new player in a room that is still in the lobby.
     *
     * @throws GameException
     * @throws ValidationException
     */
    public function handle(Room $room, string $name): Player
    {
        if (! $room->isInLobby()) {
            throw GameException::gameInProgress();
        }

        if ($room->players()->count() >= Room::MAX_PLAYERS) {
            throw GameException::roomFull();
        }

        $validated = Validator::make(['name' => trim($name)], [
            'name' => ['required', 'string', 'min:2', 'max:'.Player::MAX_NAME_LENGTH],
        ])->validate();

        if ($this->nameIsTaken($room, $validated['name'])) {
            throw ValidationException::withMessages([
                'name' => __('That name is already taken in this room.'),
            ]);
        }

        $player = $room->players()->create([
            'name' => $validated['name'],
            'score' => 0,
            'is_ready' => false,
            'is_connected' => true,
            'last_seen_at' => now(),
        ]);

        $this->announce($room, 'player-joined');

        return $player;
    }

    private function nameIsTaken(Room $room, string $name): bool
    {
        return $room->players()
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->exists();
    }
}
