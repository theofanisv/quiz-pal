<?php

namespace App\Actions\Questions;

use App\Models\Category;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Import bilingual questions from JSON files. Running it again updates the
 * existing rows instead of duplicating them, so files can be edited and re-imported.
 *
 * @phpstan-type QuestionData array{text: array{en: string, el: string}, answers: array{en: array<int, string>, el: array<int, string>}, correct: int, difficulty?: int}
 * @phpstan-type CategoryData array{slug: string, order?: int, name: array{en: string, el: string}}
 */
class ImportQuestions
{
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
     * Import one file and return how many questions it holds.
     */
    public function handleFile(string $path): int
    {
        $data = $this->parse($path);

        return DB::transaction(function () use ($data, $path): int {
            $category = Category::query()->updateOrCreate(
                ['slug' => $data['category']['slug']],
                [
                    'name_en' => $data['category']['name']['en'],
                    'name_el' => $data['category']['name']['el'],
                    'sort_order' => $data['category']['order'] ?? 0,
                ],
            );

            foreach ($data['questions'] as $index => $question) {
                $this->validateQuestion($question, $path, $index);

                Question::query()->updateOrCreate(
                    ['category_id' => $category->id, 'text_en' => $question['text']['en']],
                    [
                        'text_el' => $question['text']['el'],
                        'answers_en' => array_values($question['answers']['en']),
                        'answers_el' => array_values($question['answers']['el']),
                        'correct_index' => $question['correct'],
                        'difficulty' => $question['difficulty'] ?? null,
                    ],
                );
            }

            return count($data['questions']);
        });
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
     * @param  array<string, mixed>  $question
     */
    private function validateQuestion(array $question, string $path, int $index): void
    {
        $fail = fn (string $reason): InvalidArgumentException => new InvalidArgumentException("{$path}, question #{$index}: {$reason}");

        foreach (['en', 'el'] as $locale) {
            $text = $question['text'][$locale] ?? null;
            $answers = $question['answers'][$locale] ?? null;

            if (! is_string($text) || trim($text) === '') {
                throw $fail("missing {$locale} text");
            }

            if (! is_array($answers) || count($answers) !== Question::ANSWER_COUNT) {
                throw $fail("needs exactly four {$locale} answers");
            }

            $trimmed = array_map(fn (mixed $answer): string => is_string($answer) ? trim($answer) : '', $answers);

            if (in_array('', $trimmed, true) || count(array_unique(array_map('mb_strtolower', $trimmed))) !== Question::ANSWER_COUNT) {
                throw $fail("{$locale} answers must be four distinct non-empty strings");
            }
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
