<?php

use App\Enums\QuestionSource;
use App\Models\Category;
use Database\Seeders\QuestionSeeder;

use function Pest\Laravel\seed;

it('seeds the built-in bank and the open trivia db snapshot', function () {
    $snapshotFiles = count(glob(database_path('data/opentdb/*.json')) ?: []);

    seed(QuestionSeeder::class);

    expect(Category::query()->forSource(QuestionSource::Custom)->count())->toBe(10)
        ->and(Category::query()->forSource(QuestionSource::OpenTdb)->count())->toBe($snapshotFiles);
});
