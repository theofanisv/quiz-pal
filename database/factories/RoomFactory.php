<?php

namespace Database\Factories;

use App\Enums\QuestionSource;
use App\Enums\RoomStatus;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('????')),
            'status' => RoomStatus::Lobby,
            'question_source' => QuestionSource::Custom,
            'total_rounds' => 10,
            'seconds_per_question' => 20,
            'current_round' => 0,
        ];
    }

    public function playing(int $currentRound = 1): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RoomStatus::Playing,
            'current_round' => $currentRound,
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RoomStatus::Finished,
            'current_round' => $attributes['total_rounds'] ?? 10,
        ]);
    }

    /**
     * Create the room with an admin player named "Admin" (or the given name).
     */
    public function withAdmin(string $name = 'Admin'): static
    {
        return $this->afterCreating(function (Room $room) use ($name): void {
            $admin = Player::factory()->for($room)->create(['name' => $name]);

            $room->update(['admin_player_id' => $admin->id]);
            $room->setRelation('admin', $admin);
        });
    }
}
