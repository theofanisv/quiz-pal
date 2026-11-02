<?php

namespace App\Console\Commands;

use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use stdClass;

class QuestionUsage extends Command
{
    protected $signature = 'quiz:question-usage';

    protected $description = 'Show per category how often games have shown its questions';

    public function handle(): int
    {
        $categories = $this->usagePerCategory();

        if ($categories->isEmpty()) {
            $this->components->info('No questions are in rotation.');

            return self::SUCCESS;
        }

        $this->table(
            ['Category', 'Source', 'Questions', 'Never shown', 'Fewest', 'Average', 'Most'],
            $categories->map(fn (stdClass $category): array => [
                $category->slug,
                $category->source,
                (int) $category->questions,
                (int) $category->never_shown,
                (int) $category->fewest,
                number_format((float) $category->average, 1),
                (int) $category->most,
            ])->all(),
        );

        $most = (int) $categories->max(fn (stdClass $category): int => (int) $category->most);

        $this->components->info(sprintf(
            '%d questions in rotation, %d never shown. Each was shown %d to %d %s.',
            $categories->sum(fn (stdClass $category): int => (int) $category->questions),
            $categories->sum(fn (stdClass $category): int => (int) $category->never_shown),
            $categories->min(fn (stdClass $category): int => (int) $category->fewest),
            $most,
            Str::plural('time', $most),
        ));

        return self::SUCCESS;
    }

    /**
     * Counts over the questions new games may use, one row per category, in the order the lobby lists them.
     *
     * @return Collection<int, stdClass>
     */
    private function usagePerCategory(): Collection
    {
        return Question::query()
            ->inRotation()
            ->join('categories', 'categories.id', '=', 'questions.category_id')
            ->selectRaw(<<<'SQL'
                categories.slug,
                categories.source,
                count(*) as questions,
                sum(case when questions.times_shown = 0 then 1 else 0 end) as never_shown,
                min(questions.times_shown) as fewest,
                avg(questions.times_shown) as average,
                max(questions.times_shown) as most
                SQL)
            ->groupBy('categories.id', 'categories.slug', 'categories.source', 'categories.sort_order', 'categories.name_en')
            ->orderBy('categories.source')
            ->orderBy('categories.sort_order')
            ->orderBy('categories.name_en')
            ->toBase()
            ->get();
    }
}
