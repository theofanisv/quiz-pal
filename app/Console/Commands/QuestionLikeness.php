<?php

namespace App\Console\Commands;

use App\Actions\Questions\MeasureQuestionLikeness;
use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Likeness from MeasureQuestionLikeness
 */
class QuestionLikeness extends Command
{
    protected $signature = 'quiz:question-likeness
        {--limit=10 : How many questions to list as most and as least liked}
        {--min-plays=2 : Only list questions played at least this many times (the categories count every play)}
        {--category= : Only this category (its slug)}';

    protected $description = 'Show which categories and questions players seem to like, read from the game history';

    public function handle(MeasureQuestionLikeness $measureQuestionLikeness): int
    {
        $likeness = $measureQuestionLikeness->handle();
        $questions = Question::query()->with('category')->whereKey($likeness->keys())->get()->keyBy('id');

        $category = $this->option('category');

        if (is_string($category) && $category !== '') {
            $likeness = $likeness->filter(fn (array $measure, int $questionId): bool => $questions[$questionId]->category->slug === $category);
        }

        if ($likeness->isEmpty()) {
            $this->components->info('No game has revealed a question yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['Category', 'Source', 'Questions', 'Plays', 'Score', 'Talk', 'Answered', 'Correct'],
            $this->categoryRows($likeness, $questions),
        );

        $minPlays = max(1, (int) $this->option('min-plays'));
        $limit = max(1, (int) $this->option('limit'));
        $listed = $likeness->filter(fn (array $measure): bool => $measure['plays'] >= $minPlays);

        if ($listed->isEmpty()) {
            $this->components->info("No question was played at least {$minPlays} times yet.");

            return self::SUCCESS;
        }

        $awaitingReview = Question::query()->awaitingReview()->whereKey($listed->keys())->pluck('id')->flip();

        $this->newLine();
        $this->line('<options=bold>Most liked</>');
        $this->questionTable($listed->take($limit)->all(), $questions, $awaitingReview);

        $leastLiked = $listed->slice($limit)->reverse()->take($limit);

        if ($leastLiked->isNotEmpty()) {
            $this->newLine();
            $this->line('<options=bold>Least liked</>');
            $this->questionTable($leastLiked->all(), $questions, $awaitingReview);
        }

        return self::SUCCESS;
    }

    /**
     * One row per category, best liked first, with the averages of its played questions.
     *
     * @param  Collection<int, Likeness>  $likeness
     * @param  EloquentCollection<int, Question>  $questions
     * @return array<int, array<int, int|string>>
     */
    private function categoryRows(Collection $likeness, EloquentCollection $questions): array
    {
        return $likeness
            ->groupBy(fn (array $measure, int $questionId): int => $questions[$questionId]->category_id, preserveKeys: true)
            ->map(fn (Collection $measures): array => [
                'category' => $questions[(int) $measures->keys()->first()]->category,
                'questions' => $measures->count(),
                'plays' => $measures->sum('plays'),
                'score' => (float) $measures->avg('score'),
                'talk' => $measures->avg('talk'),
                'participation' => $measures->avg('participation'),
                'correct_rate' => $measures->avg('correct_rate'),
            ])
            ->sortByDesc('score')
            ->map(fn (array $row): array => [
                $row['category']->slug,
                $row['category']->source->value,
                $row['questions'],
                $row['plays'],
                number_format($row['score'], 1),
                $this->times($row['talk']),
                $this->percent($row['participation']),
                $this->percent($row['correct_rate']),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, Likeness>  $likeness
     * @param  EloquentCollection<int, Question>  $questions
     * @param  Collection<int, int>  $awaitingReview  flipped ids of questions awaiting review
     */
    private function questionTable(array $likeness, EloquentCollection $questions, Collection $awaitingReview): void
    {
        $this->table(
            ['ID', 'Category', 'Question', 'Plays', 'Score', 'Talk', 'Answered', 'Correct', 'Gotcha'],
            collect($likeness)->map(fn (array $measure, int $questionId): array => [
                $questionId,
                $questions[$questionId]->category->slug,
                Str::limit($questions[$questionId]->text, 60).($awaitingReview->has($questionId) ? ' [reported]' : ''),
                $measure['plays'],
                number_format($measure['score'], 1),
                $this->times($measure['talk']),
                $this->percent($measure['participation']),
                $this->percent($measure['correct_rate']),
                $this->percent($measure['wrong_spread']),
            ])->values()->all(),
        );
    }

    private function times(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 1).'x' : '-';
    }

    private function percent(mixed $value): string
    {
        return is_numeric($value) ? round((float) $value * 100).'%' : '-';
    }
}
