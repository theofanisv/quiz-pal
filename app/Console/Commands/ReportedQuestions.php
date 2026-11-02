<?php

namespace App\Console\Commands;

use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * @phpstan-type ReportedQuestion array{id: int, category: array{slug: string, source: string}, reported_at: string|null, verified_at: string|null, text: array{en: string, el: string|null}, answers: array{en: array<int, string>, el: array<int, string>|null}, correct: int}
 */
class ReportedQuestions extends Command
{
    protected $signature = 'quiz:reported-questions {--json : Print the questions as JSON, in the shape of the question files}';

    protected $description = 'List the questions players reported that still wait for review';

    public function handle(): int
    {
        $questions = Question::query()
            ->awaitingReview()
            ->with('category')
            ->orderBy('reported_at')
            ->orderBy('id')
            ->get();

        if ($this->option('json')) {
            $this->line(json_encode(
                $questions->map(fn (Question $question): array => $this->describe($question))->values()->all(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return self::SUCCESS;
        }

        if ($questions->isEmpty()) {
            $this->components->info('No reported questions wait for review.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Category', 'Reported at', 'Question', 'Correct answer'],
            $questions->map(fn (Question $question): array => [
                $question->id,
                $question->category->slug,
                $question->reported_at?->toDateTimeString(),
                Str::limit($question->text_en, 70),
                $question->answers_en[$question->correct_index] ?? '?',
            ])->all(),
        );

        $this->components->info(sprintf(
            '%d %s %s for review.',
            $questions->count(),
            Str::plural('question', $questions->count()),
            $questions->count() === 1 ? 'waits' : 'wait',
        ));

        return self::SUCCESS;
    }

    /**
     * @return ReportedQuestion
     */
    private function describe(Question $question): array
    {
        return [
            'id' => $question->id,
            'category' => [
                'slug' => $question->category->slug,
                'source' => $question->category->source->value,
            ],
            'reported_at' => $question->reported_at?->toIso8601String(),
            'verified_at' => $question->verified_at?->toIso8601String(),
            'text' => ['en' => $question->text_en, 'el' => $question->text_el],
            'answers' => ['en' => $question->answers_en, 'el' => $question->answers_el],
            'correct' => $question->correct_index,
        ];
    }
}
