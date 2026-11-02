<?php

namespace Database\Factories;

use App\Models\Answer;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'player_id' => Player::factory(),
            'choice' => 0,
            'is_correct' => false,
            'points' => 0,
            'elapsed_ms' => 0,
            'answered_at' => now(),
        ];
    }
}
