<?php

namespace Database\Factories;

use App\Enums\QuestionSource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word();

        return [
            'slug' => Str::slug($name),
            'source' => QuestionSource::Custom->value,
            'external_id' => null,
            'name_en' => Str::title($name),
            'name_el' => 'Κατηγορία '.Str::title($name),
            'sort_order' => 0,
        ];
    }

    /**
     * A category mirrored from Open Trivia DB, identified by its OpenTDB id.
     */
    public function openTdb(int $externalId = 9): static
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => "opentdb-{$externalId}",
            'source' => QuestionSource::OpenTdb->value,
            'external_id' => $externalId,
            'sort_order' => $externalId,
        ]);
    }
}
