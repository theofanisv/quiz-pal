<?php

namespace App\Actions\Questions;

use App\Enums\QuestionSource;
use App\Models\Category;
use App\Models\Question;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Write the Open Trivia DB questions of the local database to one JSON file per category,
 * in the format `ImportQuestions` reads, so the snapshot can be committed and translated.
 *
 * The English text identifies a question. A Greek translation already in the file is kept
 * only while the English text and answers are unchanged, because the Greek answers are
 * aligned with the English ones by position. Questions that are in the file but no longer
 * in the local database are kept: the committed file is the source of truth.
 *
 * @phpstan-type SnapshotQuestion array{text: array{en: string, el?: string}, answers: array{en: list<string>, el?: list<string>}, correct: int, difficulty?: int}
 */
class ExportOpenTdbQuestions
{
    public const string ATTRIBUTION = 'Open Trivia Database (https://opentdb.com), CC BY-SA 4.0';

    private const int JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * @param  list<int>|null  $onlyExternalIds  Restrict the export to these OpenTDB category ids.
     * @return array{files: int, questions: int, translated: int, retranslate: int}
     */
    public function handle(string $directory, ?array $onlyExternalIds = null): array
    {
        $query = Category::query()->forSource(QuestionSource::OpenTdb);

        if ($onlyExternalIds !== null) {
            $query->whereIn('external_id', $onlyExternalIds);
        }

        $categories = $query->get();
        $summary = ['files' => 0, 'questions' => 0, 'translated' => 0, 'retranslate' => 0];

        if ($categories->isEmpty()) {
            return $summary;
        }

        File::ensureDirectoryExists($directory);

        foreach ($categories as $category) {
            $path = $directory.'/'.$this->fileName($category);
            $questions = $this->existingQuestions($path);

            foreach ($category->questions()->get() as $question) {
                [$snapshot, $retranslate] = $this->mergeQuestion($question, $questions[$this->key($question->text_en)] ?? null);

                $questions[$this->key($question->text_en)] = $snapshot;
                $summary['retranslate'] += $retranslate ? 1 : 0;
            }

            ksort($questions, SORT_STRING);
            $questions = array_values($questions);

            File::put($path, $this->encode($category, $questions));

            $summary['files']++;
            $summary['questions'] += count($questions);
            $summary['translated'] += count(array_filter($questions, fn (array $question): bool => isset($question['text']['el'])));
        }

        return $summary;
    }

    private function fileName(Category $category): string
    {
        return sprintf('%02d-%s.json', (int) $category->external_id, Str::slug($category->name_en));
    }

    private function key(string $text): string
    {
        return mb_strtolower($text);
    }

    /**
     * The questions already in the file, keyed by their lower-cased English text.
     *
     * @return array<string, SnapshotQuestion>
     */
    private function existingQuestions(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ! is_array($data['questions'] ?? null)) {
            throw new RuntimeException("{$path} is not a valid question file.");
        }

        $questions = [];

        /** @var SnapshotQuestion $question */
        foreach ($data['questions'] as $question) {
            $questions[$this->key($question['text']['en'])] = $question;
        }

        return $questions;
    }

    /**
     * The database row wins for the English fields; the file keeps the Greek translation
     * while the English text and answers are unchanged.
     *
     * @param  SnapshotQuestion|null  $fileQuestion
     * @return array{0: SnapshotQuestion, 1: bool} the question to write and whether a translation was dropped
     */
    private function mergeQuestion(Question $question, ?array $fileQuestion): array
    {
        $answersEn = array_values(array_map(strval(...), (array) $question->answers_en));
        $greekText = $question->text_el;
        $greekAnswers = $question->answers_el;
        $retranslate = false;

        if ($fileQuestion !== null && isset($fileQuestion['text']['el'], $fileQuestion['answers']['el'])) {
            $unchanged = $fileQuestion['text']['en'] === $question->text_en
                && array_map(strval(...), $fileQuestion['answers']['en']) === $answersEn;

            [$greekText, $greekAnswers] = $unchanged
                ? [$fileQuestion['text']['el'], $fileQuestion['answers']['el']]
                : [null, null];
            $retranslate = ! $unchanged;
        }

        $snapshot = [
            'text' => ['en' => $question->text_en],
            'answers' => ['en' => $answersEn],
            'correct' => $question->correct_index,
        ];

        if (is_string($greekText) && is_array($greekAnswers)) {
            $snapshot['text']['el'] = $greekText;
            $snapshot['answers']['el'] = array_values(array_map(strval(...), $greekAnswers));
        }

        if ($question->difficulty !== null) {
            $snapshot['difficulty'] = $question->difficulty;
        }

        return [$snapshot, $retranslate];
    }

    /**
     * One question per line, so re-exports and translation batches diff cleanly.
     *
     * @param  list<SnapshotQuestion>  $questions
     */
    private function encode(Category $category, array $questions): string
    {
        $header = [
            'slug' => $category->slug,
            'order' => $category->sort_order,
            'source' => QuestionSource::OpenTdb->value,
            'external_id' => $category->external_id,
            'name' => ['en' => $category->name_en, 'el' => $category->name_el],
        ];
        $headerJson = (string) preg_replace('/^/m', '    ', json_encode($header, self::JSON_FLAGS | JSON_PRETTY_PRINT));
        $lines = array_map(fn (array $question): string => '        '.json_encode($question, self::JSON_FLAGS), $questions);

        return "{\n"
            .'    "attribution": '.json_encode(self::ATTRIBUTION, self::JSON_FLAGS).",\n"
            .'    "category": '.ltrim($headerJson).",\n"
            ."    \"questions\": [\n"
            .implode(",\n", $lines)."\n"
            ."    ]\n"
            ."}\n";
    }
}
