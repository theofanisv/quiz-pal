<?php

namespace App\Console\Commands;

use App\Actions\Questions\ExportOpenTdbQuestions as ExportOpenTdbQuestionsAction;
use Illuminate\Console\Command;

class ExportOpenTdbQuestions extends Command
{
    protected $signature = 'quiz:export-opentdb
                            {path? : Directory for the JSON files (defaults to database/data/opentdb)}
                            {--category=* : Only these Open Trivia DB category ids}';

    protected $description = 'Write the synced Open Trivia DB questions to JSON files that can be committed and translated';

    public function handle(ExportOpenTdbQuestionsAction $export): int
    {
        $path = $this->argument('path');
        /** @var list<string> $categories */
        $categories = (array) $this->option('category');

        $summary = $export->handle(
            is_string($path) ? $path : database_path('data/opentdb'),
            $categories === [] ? null : array_map(intval(...), $categories),
        );

        if ($summary['files'] === 0) {
            $this->components->error('No Open Trivia DB categories in the database yet: run php artisan quiz:sync-opentdb first.');

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Exported %d questions to %d file(s) (%d translated, %d to retranslate).',
            $summary['questions'],
            $summary['files'],
            $summary['translated'],
            $summary['retranslate'],
        ));

        return self::SUCCESS;
    }
}
