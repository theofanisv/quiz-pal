<?php

use App\Models\Category;

use function Pest\Laravel\artisan;

it('imports both bundled folders when no path is given', function () {
    $snapshotFiles = count(glob(database_path('data/opentdb/*.json')) ?: []);

    artisan('quiz:import-questions')
        ->expectsOutputToContain('from '.(10 + $snapshotFiles).' file(s).')
        ->assertSuccessful();

    expect(Category::query()->count())->toBe(10 + $snapshotFiles);
});

it('imports a single file when given its path', function () {
    artisan('quiz:import-questions', ['path' => base_path('tests/Fixtures/questions/opentdb-sample.json')])
        ->expectsOutputToContain('Imported 2 questions from 1 file(s).')
        ->assertSuccessful();

    expect(Category::query()->where('slug', 'opentdb-13')->exists())->toBeTrue();
});
