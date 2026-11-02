<?php

namespace App\Actions\Game;

use App\Actions\Game\Concerns\BroadcastsRoomUpdates;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Database\UniqueConstraintViolationException;
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

        $player = $this->createPlayer($room, $validated['name']);

        $this->announce($room, 'player-joined');

        return $player;
    }

    /**
     * The player's colour is picked at insert time (see Player::booted). Two people
     * joining at the same instant can pick the same one; the unique index catches
     * that and the second insert simply picks again.
     */
    private function createPlayer(Room $room, string $name, bool $retry = true): Player
    {
        try {
            return $room->players()->create([
                'name' => $name,
                'score' => 0,
                'is_ready' => false,
                'is_connected' => true,
                'last_seen_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            if (! $retry) {
                throw $exception;
            }

            return $this->createPlayer($room, $name, retry: false);
        }
    }

    private function nameIsTaken(Room $room, string $name): bool
    {
        return $room->players()
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->exists();
    }
}
