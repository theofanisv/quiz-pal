<?php

use App\Actions\Questions\ImportQuestions;
use App\Models\Category;
use App\Models\Question;

use function Pest\Laravel\assertDatabaseCount;

it('imports a category with its questions from a json file', function () {
    $count = app(ImportQuestions::class)->handleFile(base_path('tests/Fixtures/questions/sample.json'));

    $category = Category::query()->where('slug', 'sample')->sole();
    $question = Question::query()->where('text_en', 'What is 2 + 2?')->sole();
    expect($count)->toBe(2)
        ->and($category->name_el)->toBe('Δείγμα')
        ->and($category->questions()->count())->toBe(2)
        ->and($question->answers_el)->toBe(['3', '4', '5', '6'])
        ->and($question->correct_index)->toBe(1)
        ->and($question->difficulty)->toBe(1);
});

it('updates existing questions instead of duplicating them when run twice', function () {
    $importer = app(ImportQuestions::class);
    $importer->handleFile(base_path('tests/Fixtures/questions/sample.json'));

    $importer->handleFile(base_path('tests/Fixtures/questions/sample.json'));

    assertDatabaseCount('categories', 1);
    assertDatabaseCount('questions', 2);
});

it('rejects a question that does not have four distinct answers in both languages', function () {
    $path = tempnam(sys_get_temp_dir(), 'questions').'.json';
    file_put_contents($path, json_encode([
        'category' => ['slug' => 'broken', 'name' => ['en' => 'Broken', 'el' => 'Χαλασμένο']],
        'questions' => [[
            'text' => ['en' => 'Pick one', 'el' => 'Διάλεξε'],
            'answers' => ['en' => ['a', 'b', 'c', 'a'], 'el' => ['α', 'β', 'γ', 'δ']],
            'correct' => 0,
        ]],
    ]));

    expect(fn () => app(ImportQuestions::class)->handleFile($path))
        ->toThrow(InvalidArgumentException::class, 'question #0: en answers must be four distinct non-empty strings');

    assertDatabaseCount('questions', 0);
});

it('imports every bundled category file and rejects duplicate questions across the dataset', function () {
    $result = app(ImportQuestions::class)->handleDirectory(database_path('data/questions'));

    expect($result['files'])->toBe(10)
        ->and($result['questions'])->toBeGreaterThanOrEqual(300)
        ->and(Question::query()->count())->toBe($result['questions'])
        ->and(Question::query()->pluck('text_en')->map(fn (string $text): string => mb_strtolower($text))->duplicates())->toBeEmpty()
        ->and(Category::query()->count())->toBe(10);
});
