<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Question;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $answers = array_map(fn (): string => fake()->unique()->word(), range(1, Question::ANSWER_COUNT));

        return [
            'category_id' => Category::factory(),
            'text_en' => fake()->unique()->sentence().'?',
            'text_el' => 'Ερώτηση: '.fake()->unique()->sentence().';',
            'answers_en' => $answers,
            'answers_el' => array_map(fn (string $answer): string => 'Απάντηση '.$answer, $answers),
            'correct_index' => fake()->numberBetween(0, Question::ANSWER_COUNT - 1),
            'difficulty' => fake()->numberBetween(1, 3),
        ];
    }

    public function correctAt(int $index): static
    {
        return $this->state(fn (array $attributes): array => ['correct_index' => $index]);
    }

    /**
     * A question a player reported and nobody has reviewed yet.
     */
    public function reported(): static
    {
        return $this->state(fn (array $attributes): array => ['reported_at' => now()->subHour()]);
    }

    /**
     * A question that was reviewed after its last report.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reported_at' => now()->subDay(),
            'verified_at' => now()->subHour(),
        ]);
    }

    /**
     * A question games have already shown, last shown an hour ago unless given.
     */
    public function shown(int $times = 1, ?CarbonInterface $lastShownAt = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'times_shown' => $times,
            'last_shown_at' => $lastShownAt ?? now()->subHour(),
        ]);
    }

    /**
     * A question that only exists in English, like the ones synced from Open Trivia DB.
     */
    public function englishOnly(): static
    {
        return $this->state(fn (array $attributes): array => ['text_el' => null, 'answers_el' => null]);
    }
}
