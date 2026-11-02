<?php

use App\Actions\Questions\ExportOpenTdbQuestions;
use App\Actions\Questions\ImportQuestions;
use App\Enums\QuestionSource;
use App\Models\Category;
use App\Models\Question;
use Illuminate\Support\Facades\File;

function exportDirectory(): string
{
    $directory = sys_get_temp_dir().'/opentdb-export-'.uniqid();
    File::ensureDirectoryExists($directory);

    return $directory;
}

/**
 * @return array<string, mixed>
 */
function readSnapshot(string $path): array
{
    return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

it('writes one file per open trivia db category in the snapshot format', function () {
    $directory = exportDirectory();
    Category::factory()->has(Question::factory())->create();
    $general = Category::factory()->openTdb(9)->create(['name_en' => 'General Knowledge', 'name_el' => 'Γενικές γνώσεις']);
    Question::factory()->for($general)->englishOnly()->create(['text_en' => 'Zebra question?', 'answers_en' => ['Right', 'Wrong 1', 'Wrong 2', 'Wrong 3'], 'correct_index' => 0, 'difficulty' => 2]);
    Question::factory()->for($general)->englishOnly()->create(['text_en' => 'apple question?', 'answers_en' => ['A', 'B', 'C', 'D'], 'correct_index' => 3, 'difficulty' => null]);
    Question::factory()->for($general)->create(['text_en' => 'Middle question?', 'text_el' => 'Μεσαία ερώτηση;', 'answers_en' => ['1', '2', '3', '4'], 'answers_el' => ['ένα', 'δύο', 'τρία', 'τέσσερα'], 'correct_index' => 1, 'difficulty' => 1]);

    $summary = app(ExportOpenTdbQuestions::class)->handle($directory);

    $path = "{$directory}/09-general-knowledge.json";
    $raw = (string) file_get_contents($path);
    $snapshot = readSnapshot($path);
    expect($summary)->toBe(['files' => 1, 'questions' => 3, 'translated' => 1, 'retranslate' => 0])
        ->and(glob("{$directory}/*.json"))->toBe([$path])
        ->and($snapshot['attribution'])->toBe('Open Trivia Database (https://opentdb.com), CC BY-SA 4.0')
        ->and($snapshot['category'])->toBe(['slug' => 'opentdb-9', 'order' => 9, 'source' => 'opentdb', 'external_id' => 9, 'name' => ['en' => 'General Knowledge', 'el' => 'Γενικές γνώσεις']])
        ->and(array_column(array_column($snapshot['questions'], 'text'), 'en'))->toBe(['apple question?', 'Middle question?', 'Zebra question?'])
        ->and($snapshot['questions'][0])->toBe(['text' => ['en' => 'apple question?'], 'answers' => ['en' => ['A', 'B', 'C', 'D']], 'correct' => 3])
        ->and($snapshot['questions'][1])->toBe(['text' => ['en' => 'Middle question?', 'el' => 'Μεσαία ερώτηση;'], 'answers' => ['en' => ['1', '2', '3', '4'], 'el' => ['ένα', 'δύο', 'τρία', 'τέσσερα']], 'correct' => 1, 'difficulty' => 1])
        ->and($snapshot['questions'][2]['difficulty'])->toBe(2)
        ->and(substr_count($raw, "\n        {\"text\":"))->toBe(3);
    File::deleteDirectory($directory);
});

it('keeps greek translations from the existing file while the english text and answers are unchanged', function () {
    $directory = exportDirectory();
    $general = Category::factory()->openTdb(9)->create(['name_en' => 'General Knowledge', 'name_el' => 'Γενικές γνώσεις']);
    Question::factory()->for($general)->englishOnly()->create(['text_en' => 'Kept?', 'answers_en' => ['a', 'b', 'c', 'd'], 'correct_index' => 0, 'difficulty' => null]);
    Question::factory()->for($general)->englishOnly()->create(['text_en' => 'Changed?', 'answers_en' => ['a', 'b', 'c', 'NEW'], 'correct_index' => 0, 'difficulty' => null]);
    file_put_contents("{$directory}/09-general-knowledge.json", json_encode([
        'category' => ['slug' => 'opentdb-9', 'order' => 9, 'source' => 'opentdb', 'external_id' => 9, 'name' => ['en' => 'General Knowledge', 'el' => 'Γενικές γνώσεις']],
        'questions' => [
            ['text' => ['en' => 'Kept?', 'el' => 'Κρατιέται;'], 'answers' => ['en' => ['a', 'b', 'c', 'd'], 'el' => ['α', 'β', 'γ', 'δ']], 'correct' => 0],
            ['text' => ['en' => 'Changed?', 'el' => 'Άλλαξε;'], 'answers' => ['en' => ['a', 'b', 'c', 'd'], 'el' => ['α', 'β', 'γ', 'δ']], 'correct' => 0],
            ['text' => ['en' => 'Only in the file?', 'el' => 'Μόνο στο αρχείο;'], 'answers' => ['en' => ['w', 'x', 'y', 'z'], 'el' => ['ω', 'χ', 'ψ', 'ζ']], 'correct' => 2, 'difficulty' => 3],
        ],
    ], JSON_UNESCAPED_UNICODE));

    $summary = app(ExportOpenTdbQuestions::class)->handle($directory);

    $questions = collect(readSnapshot("{$directory}/09-general-knowledge.json")['questions'])->keyBy('text.en');
    expect($summary)->toBe(['files' => 1, 'questions' => 3, 'translated' => 2, 'retranslate' => 1])
        ->and($questions->get('Kept?')['text']['el'])->toBe('Κρατιέται;')
        ->and($questions->get('Changed?'))->toBe(['text' => ['en' => 'Changed?'], 'answers' => ['en' => ['a', 'b', 'c', 'NEW']], 'correct' => 0])
        ->and($questions->get('Only in the file?'))->toBe(['text' => ['en' => 'Only in the file?', 'el' => 'Μόνο στο αρχείο;'], 'answers' => ['en' => ['w', 'x', 'y', 'z'], 'el' => ['ω', 'χ', 'ψ', 'ζ']], 'correct' => 2, 'difficulty' => 3]);
    File::deleteDirectory($directory);
});

it('exports only the requested categories', function () {
    $directory = exportDirectory();
    Category::factory()->openTdb(9)->has(Question::factory()->englishOnly())->create(['name_en' => 'General Knowledge']);
    Category::factory()->openTdb(13)->has(Question::factory()->englishOnly())->create(['name_en' => 'Entertainment: Musicals & Theatres']);

    $summary = app(ExportOpenTdbQuestions::class)->handle($directory, [13]);

    expect($summary['files'])->toBe(1)
        ->and(glob("{$directory}/*.json"))->toBe(["{$directory}/13-entertainment-musicals-theatres.json"]);
    File::deleteDirectory($directory);
});

it('writes files the importer reads back unchanged', function () {
    $directory = exportDirectory();
    $general = Category::factory()->openTdb(9)->create(['name_en' => 'General Knowledge', 'name_el' => 'Γενικές γνώσεις']);
    Question::factory()->for($general)->englishOnly()->create(['text_en' => 'Plain?', 'answers_en' => ['a', 'b', 'c', 'd'], 'correct_index' => 2, 'difficulty' => 3]);
    Question::factory()->for($general)->create(['text_en' => 'Translated?', 'text_el' => 'Μεταφρασμένη;', 'answers_en' => ['1', '2', '3', '4'], 'answers_el' => ['α', 'β', 'γ', 'δ'], 'correct_index' => 0, 'difficulty' => null]);
    $columns = ['text_en', 'text_el', 'answers_en', 'answers_el', 'correct_index', 'difficulty'];
    $before = Question::query()->orderBy('text_en')->get($columns)->toArray();
    app(ExportOpenTdbQuestions::class)->handle($directory);
    Question::query()->delete();
    Category::query()->delete();

    app(ImportQuestions::class)->handleFile("{$directory}/09-general-knowledge.json");

    $category = Category::query()->sole();
    expect($category->source)->toBe(QuestionSource::OpenTdb)
        ->and($category->external_id)->toBe(9)
        ->and($category->slug)->toBe('opentdb-9')
        ->and($category->name_el)->toBe('Γενικές γνώσεις')
        ->and(Question::query()->orderBy('text_en')->get($columns)->toArray())->toBe($before);
    File::deleteDirectory($directory);
});

it('exports nothing when no open trivia db category exists', function () {
    $directory = exportDirectory();
    Category::factory()->has(Question::factory())->create();

    expect(app(ExportOpenTdbQuestions::class)->handle($directory))->toBe(['files' => 0, 'questions' => 0, 'translated' => 0, 'retranslate' => 0])
        ->and(glob("{$directory}/*.json"))->toBe([]);
    File::deleteDirectory($directory);
});

it('finds the existing file by category id when the category was renamed, and replaces it', function () {
    $directory = exportDirectory();
    $general = Category::factory()->openTdb(9)->create(['name_en' => 'General Knowledge', 'name_el' => 'Γενικές γνώσεις']);
    Question::factory()->for($general)->englishOnly()->create(['text_en' => 'Kept?', 'answers_en' => ['a', 'b', 'c', 'd'], 'correct_index' => 0, 'difficulty' => null]);
    file_put_contents("{$directory}/09-general.json", json_encode([
        'category' => ['slug' => 'opentdb-9', 'order' => 9, 'source' => 'opentdb', 'external_id' => 9, 'name' => ['en' => 'General', 'el' => 'Γενικά']],
        'questions' => [['text' => ['en' => 'Kept?', 'el' => 'Κρατιέται;'], 'answers' => ['en' => ['a', 'b', 'c', 'd'], 'el' => ['α', 'β', 'γ', 'δ']], 'correct' => 0]],
    ], JSON_UNESCAPED_UNICODE));

    app(ExportOpenTdbQuestions::class)->handle($directory);

    expect(glob("{$directory}/*.json"))->toBe(["{$directory}/09-general-knowledge.json"])
        ->and(readSnapshot("{$directory}/09-general-knowledge.json")['questions'][0]['text']['el'])->toBe('Κρατιέται;');
    File::deleteDirectory($directory);
});
