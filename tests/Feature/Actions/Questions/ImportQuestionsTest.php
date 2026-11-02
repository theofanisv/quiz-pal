<?php

use App\Actions\Questions\ImportQuestions;
use App\Enums\QuestionSource;
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

/**
 * Write a question file to a temporary path.
 *
 * @param  array<string, mixed>  $data
 */
function writeQuestionFile(array $data): string
{
    $path = tempnam(sys_get_temp_dir(), 'questions').'.json';
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));

    return $path;
}

it('imports an open trivia db snapshot file, tagging the category and accepting english-only questions', function () {
    $count = app(ImportQuestions::class)->handleFile(base_path('tests/Fixtures/questions/opentdb-sample.json'));

    $category = Category::query()->where('slug', 'opentdb-13')->sole();
    $englishOnly = Question::query()->where('text_en', 'Who composed the musical "Cats"?')->sole();
    $translated = Question::query()->where('text_en', 'In which city is the musical "Chicago" set?')->sole();
    expect($count)->toBe(2)
        ->and($category->source)->toBe(QuestionSource::OpenTdb)
        ->and($category->external_id)->toBe(13)
        ->and($category->name_el)->toBe('Ψυχαγωγία: Μιούζικαλ & Θέατρο')
        ->and($englishOnly->text_el)->toBeNull()
        ->and($englishOnly->answers_el)->toBeNull()
        ->and($englishOnly->answers_en)->toBe(['Andrew Lloyd Webber', 'Stephen Sondheim', 'Lin-Manuel Miranda', 'Claude-Michel Schönberg'])
        ->and($englishOnly->correct_index)->toBe(0)
        ->and($translated->text_el)->toBe('Σε ποια πόλη διαδραματίζεται το μιούζικαλ «Chicago»;')
        ->and($translated->answers_el)->toBe(['Σικάγο', 'Νέα Υόρκη', 'Βοστώνη', 'Λος Άντζελες']);
});

it('still requires greek text for the built-in questions', function () {
    $path = writeQuestionFile([
        'category' => ['slug' => 'builtin', 'name' => ['en' => 'Built-in', 'el' => 'Ενσωματωμένη']],
        'questions' => [['text' => ['en' => 'Pick one'], 'answers' => ['en' => ['a', 'b', 'c', 'd']], 'correct' => 0]],
    ]);

    expect(fn () => app(ImportQuestions::class)->handleFile($path))
        ->toThrow(InvalidArgumentException::class, 'missing el text');
});

it('rejects a snapshot question whose greek text comes without greek answers', function () {
    $path = writeQuestionFile([
        'category' => ['slug' => 'opentdb-9', 'source' => 'opentdb', 'external_id' => 9, 'name' => ['en' => 'General', 'el' => 'Γενικά']],
        'questions' => [['text' => ['en' => 'Pick one', 'el' => 'Διάλεξε'], 'answers' => ['en' => ['a', 'b', 'c', 'd']], 'correct' => 0]],
    ]);

    expect(fn () => app(ImportQuestions::class)->handleFile($path))
        ->toThrow(InvalidArgumentException::class, 'el answers');
});

it('rejects an unknown question source', function () {
    $path = writeQuestionFile([
        'category' => ['slug' => 'wiki', 'source' => 'wikipedia', 'name' => ['en' => 'Wiki', 'el' => 'Βίκι']],
        'questions' => [],
    ]);

    expect(fn () => app(ImportQuestions::class)->handleFile($path))
        ->toThrow(InvalidArgumentException::class, 'source');
});

it('imports the built-in bank and the open trivia db snapshot together', function () {
    $snapshotFiles = count(glob(database_path('data/opentdb/*.json')) ?: []);

    $result = app(ImportQuestions::class)->handleBundled();

    expect($result['files'])->toBe(10 + $snapshotFiles)
        ->and(Category::query()->forSource(QuestionSource::Custom)->count())->toBe(10)
        ->and(Category::query()->forSource(QuestionSource::OpenTdb)->count())->toBe($snapshotFiles);
});

it('imports every bundled open trivia db snapshot file', function () {
    $result = app(ImportQuestions::class)->handleDirectory(database_path('data/opentdb'));

    $categories = Category::query()->get();
    expect($result['files'])->toBe(24)
        ->and($categories)->toHaveCount(24)
        ->and($categories->every(fn (Category $category): bool => $category->source === QuestionSource::OpenTdb && $category->external_id !== null))->toBeTrue()
        ->and(Question::query()->count())->toBe($result['questions']);
});

it('rejects an open trivia db file whose slug does not match its external id', function () {
    $path = writeQuestionFile([
        'category' => ['slug' => 'opentdb-09', 'source' => 'opentdb', 'external_id' => 9, 'name' => ['en' => 'General', 'el' => 'Γενικά']],
        'questions' => [],
    ]);

    expect(fn () => app(ImportQuestions::class)->handleFile($path))
        ->toThrow(InvalidArgumentException::class, 'opentdb-9');
});

it('rejects an open trivia db file without a numeric external id', function () {
    $path = writeQuestionFile([
        'category' => ['slug' => 'opentdb-9', 'source' => 'opentdb', 'external_id' => '9', 'name' => ['en' => 'General', 'el' => 'Γενικά']],
        'questions' => [],
    ]);

    expect(fn () => app(ImportQuestions::class)->handleFile($path))
        ->toThrow(InvalidArgumentException::class, 'external_id');
});

it('rejects question text longer than the column allows', function () {
    $path = writeQuestionFile([
        'category' => ['slug' => 'long', 'name' => ['en' => 'Long', 'el' => 'Μακρύ']],
        'questions' => [['text' => ['en' => 'Short?', 'el' => str_repeat('α', 501)], 'answers' => ['en' => ['a', 'b', 'c', 'd'], 'el' => ['α', 'β', 'γ', 'δ']], 'correct' => 0]],
    ]);

    expect(fn () => app(ImportQuestions::class)->handleFile($path))
        ->toThrow(InvalidArgumentException::class, '500');
});
