<?php

namespace App\Actions\Game;

use App\Enums\QuestionSource;
use App\Enums\RoomStatus;
use App\Models\Category;
use App\Models\Room;
use App\Support\RoomCode;
use Illuminate\Support\Facades\DB;

class CreateRoom
{
    public function __construct(private JoinRoom $joinRoom) {}

    /**
     * Create a room whose first player, the creator, becomes its admin.
     * It plays the built-in questions with every category selected until the admin changes that.
     */
    public function handle(string $adminName): Room
    {
        return DB::transaction(function () use ($adminName): Room {
            $room = Room::create([
                'code' => $this->uniqueCode(),
                'status' => RoomStatus::Lobby,
                'question_source' => QuestionSource::Custom,
                'total_rounds' => 10,
                'seconds_per_question' => 20,
                'current_round' => 0,
            ]);

            $admin = $this->joinRoom->handle($room, $adminName);

            $room->update(['admin_player_id' => $admin->id]);
            $room->categories()->attach(Category::query()->forSource(QuestionSource::Custom)->pluck('id'));

            return $room;
        });
    }

    private function uniqueCode(): string
    {
        do {
            $code = RoomCode::generate();
        } while (Room::query()->where('code', $code)->exists());

        return $code;
    }
}
