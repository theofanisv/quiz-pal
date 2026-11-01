<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Question;
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
}
