<?php

namespace Database\Factories;

use App\Enums\QuestionSource;
use App\Models\Game;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'question_source' => QuestionSource::Custom,
            'total_rounds' => 10,
            'seconds_per_question' => 20,
            'finished_at' => null,
        ];
    }

    /**
     * A game of the given room, played with the room's current settings.
     */
    public function forRoom(Room $room): static
    {
        return $this->for($room)->state([
            'question_source' => $room->question_source,
            'total_rounds' => $room->total_rounds,
            'seconds_per_question' => $room->seconds_per_question,
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes): array => [
            'finished_at' => now(),
        ]);
    }
}
