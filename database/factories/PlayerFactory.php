<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'name' => fake()->unique()->firstName(),
            'score' => 0,
            'is_ready' => false,
            'is_connected' => true,
            'last_seen_at' => now(),
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => ['is_ready' => true]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (array $attributes): array => ['is_connected' => false]);
    }
}
