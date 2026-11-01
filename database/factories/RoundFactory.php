<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Room;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Round>
 */
class RoundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'round_number' => 1,
            'question_id' => Question::factory(),
            'answer_order' => [0, 1, 2, 3],
            'started_at' => null,
            'ends_at' => null,
            'revealed_at' => null,
        ];
    }

    /**
     * A round that started now and times out after the given number of seconds.
     */
    public function started(int $seconds = 20): static
    {
        return $this->state(fn (array $attributes): array => [
            'started_at' => now(),
            'ends_at' => now()->addSeconds($seconds),
        ]);
    }

    public function revealed(): static
    {
        return $this->started()->state(fn (array $attributes): array => [
            'revealed_at' => now(),
        ]);
    }
}
