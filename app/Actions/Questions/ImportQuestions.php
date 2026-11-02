<?php

namespace App\Actions\Questions;

use App\Enums\QuestionSource;
use App\Models\Category;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Import questions from JSON files. Running it again updates the existing rows instead of
 * duplicating them, so files can be edited and re-imported.
 *
 * Built-in files are bilingual. Snapshot files mirrored from Open Trivia DB (category source
 * "opentdb") may hold English-only questions until they are translated; those are stored
 * without Greek text and shown in English.
 *
 * The English text identifies a question. To reword it without losing the row (its rounds and
 * review state), give the old English text as "replaces"; the row is renamed in place.
 *
 * @phpstan-type QuestionData array{text: array{en: string, el?: string}, answers: array{en: array<int, string>, el?: array<int, string>}, correct: int, difficulty?: int, replaces?: string}
 * @phpstan-type CategoryData array{slug: string, order?: int, source?: mixed, external_id?: mixed, name: array{en: string, el: string}}
 */
class ImportQuestions
{
    private const int MAX_TEXT_LENGTH = 500;

    /**
     * Import every *.json file in the directory, in file-name order.
     *
     * @return array{files: int, questions: int}
     */
    public function handleDirectory(string $directory): array
    {
        $files = glob(rtrim($directory, '/').'/*.json');

        if ($files === false || $files === []) {
            throw new InvalidArgumentException("No question files found in {$directory}.");
        }

        sort($files);

        $questions = 0;

        foreach ($files as $file) {
            $questions += $this->handleFile($file);
        }

        return ['files' => count($files), 'questions' => $questions];
    }

    /**
     * Import the files bundled with the application: the built-in bank and, when present,
     * the Open Trivia DB snapshot.
     *
     * @return array{files: int, questions: int}
     */
    public function handleBundled(): array
    {
        $result = $this->handleDirectory(database_path('data/questions'));

        if (is_dir(database_path('data/opentdb'))) {
            $snapshot = $this->handleDirectory(database_path('data/opentdb'));
            $result['files'] += $snapshot['files'];
            $result['questions'] += $snapshot['questions'];
        }

        return $result;
    }

    /**
     * Import one file and return how many questions it holds.
     */
    public function handleFile(string $path): int
    {
        $data = $this->parse($path);
        $source = $this->source($data['category'], $path);
        $this->validateCategory($data['category'], $source, $path);

        return DB::transaction(function () use ($data, $path, $source): int {
            $externalId = $data['category']['external_id'] ?? null;

            $category = Category::query()->updateOrCreate(
                ['slug' => $data['category']['slug']],
                [
                    'source' => $source,
                    'external_id' => is_int($externalId) ? $externalId : null,
                    'name_en' => $data['category']['name']['en'],
                    'name_el' => $data['category']['name']['el'],
                    'sort_order' => $data['category']['order'] ?? 0,
                ],
            );

            foreach ($data['questions'] as $index => $question) {
                $this->validateQuestion($question, $source, $path, $index);

                $this->findQuestion($category, $question)->fill([
                    'text_en' => $question['text']['en'],
                    'text_el' => $question['text']['el'] ?? null,
                    'answers_en' => array_values($question['answers']['en']),
                    'answers_el' => isset($question['answers']['el']) ? array_values($question['answers']['el']) : null,
                    'correct_index' => $question['correct'],
                    'difficulty' => $question['difficulty'] ?? null,
                ])->save();
            }

            return count($data['questions']);
        });
    }

    /**
     * The row with the question's English text, else the row it replaces, else a new row.
     *
     * @param  QuestionData  $question
     */
    private function findQuestion(Category $category, array $question): Question
    {
        $texts = array_filter([$question['text']['en'], $question['replaces'] ?? null]);

        foreach ($texts as $text) {
            $existing = $category->questions()->where('text_en', $text)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return new Question(['category_id' => $category->id]);
    }

    /**
     * @return array{category: CategoryData, questions: array<int, QuestionData>}
     */
    private function parse(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException("Cannot read {$path}.");
        }

        $data = json_decode($contents, true);

        if (! is_array($data) || ! isset($data['category']['slug'], $data['category']['name']['en'], $data['category']['name']['el']) || ! is_array($data['questions'] ?? null)) {
            throw new InvalidArgumentException("{$path} is not a valid question file.");
        }

        /** @var array{category: CategoryData, questions: array<int, QuestionData>} $data */
        return $data;
    }

    /**
     * @param  CategoryData  $category
     */
    private function source(array $category, string $path): QuestionSource
    {
        $value = $category['source'] ?? QuestionSource::Custom->value;
        $source = is_string($value) ? QuestionSource::tryFrom($value) : null;

        if ($source === null) {
            throw new InvalidArgumentException("{$path}: unknown question source ".json_encode($value).'.');
        }

        return $source;
    }

    /**
     * Open Trivia DB files are identified by the OpenTDB category id, which the slug must carry.
     *
     * @param  CategoryData  $category
     */
    private function validateCategory(array $category, QuestionSource $source, string $path): void
    {
        if ($source !== QuestionSource::OpenTdb) {
            return;
        }

        $externalId = $category['external_id'] ?? null;

        if (! is_int($externalId) || $externalId < 1) {
            throw new InvalidArgumentException("{$path}: an Open Trivia DB file needs a positive integer \"external_id\".");
        }

        if ($category['slug'] !== "opentdb-{$externalId}") {
            throw new InvalidArgumentException("{$path}: the slug of Open Trivia DB category {$externalId} must be \"opentdb-{$externalId}\".");
        }
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function validateQuestion(array $question, QuestionSource $source, string $path, int $index): void
    {
        $fail = fn (string $reason): InvalidArgumentException => new InvalidArgumentException("{$path}, question #{$index}: {$reason}");

        $hasGreek = isset($question['text']['el']) || isset($question['answers']['el']);
        $locales = $source === QuestionSource::OpenTdb && ! $hasGreek ? ['en'] : ['en', 'el'];

        foreach ($locales as $locale) {
            $text = $question['text'][$locale] ?? null;
            $answers = $question['answers'][$locale] ?? null;

            if (! is_string($text) || trim($text) === '') {
                throw $fail("missing {$locale} text");
            }

            if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
                throw $fail("{$locale} text is longer than ".self::MAX_TEXT_LENGTH.' characters');
            }

            if (! is_array($answers) || count($answers) !== Question::ANSWER_COUNT) {
                throw $fail("needs exactly four {$locale} answers");
            }

            $trimmed = array_map(fn (mixed $answer): string => is_string($answer) ? trim($answer) : '', $answers);

            if (in_array('', $trimmed, true) || count(array_unique(array_map('mb_strtolower', $trimmed))) !== Question::ANSWER_COUNT) {
                throw $fail("{$locale} answers must be four distinct non-empty strings");
            }
        }

        $replaces = $question['replaces'] ?? null;

        if ($replaces !== null && (! is_string($replaces) || trim($replaces) === '')) {
            throw $fail('"replaces" must be the old English text of the question');
        }

        $correct = $question['correct'] ?? null;

        if (! is_int($correct) || $correct < 0 || $correct >= Question::ANSWER_COUNT) {
            throw $fail('"correct" must be an index from 0 to 3');
        }

        $difficulty = $question['difficulty'] ?? null;

        if ($difficulty !== null && (! is_int($difficulty) || $difficulty < 1 || $difficulty > 3)) {
            throw $fail('"difficulty" must be 1, 2 or 3');
        }
    }
}
