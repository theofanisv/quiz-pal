<?php

namespace App\Console\Commands;

use App\Actions\Questions\SyncOpenTdbQuestions as SyncOpenTdbQuestionsAction;
use Illuminate\Console\Command;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Str;
use RuntimeException;

class SyncOpenTdbQuestions extends Command
{
    protected $signature = 'quiz:sync-opentdb {--category=* : Only these Open Trivia DB category ids}';

    protected $description = 'Mirror the multiple-choice questions of Open Trivia DB into the local question bank (one request every 5 seconds, so a full run takes about 20 minutes)';

    public function handle(SyncOpenTdbQuestionsAction $sync): int
    {
        /** @var list<string> $categories */
        $categories = (array) $this->option('category');
        $onlyExternalIds = $categories === [] ? null : array_map(intval(...), $categories);

        try {
            $summary = $sync->handle($onlyExternalIds);
        } catch (RuntimeException|HttpClientException $exception) {
            report($exception);

            $this->components->error("Open Trivia DB sync stopped: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Synced %d %s in %d %s from Open Trivia DB (%d skipped).',
            $summary['questions'],
            Str::plural('question', $summary['questions']),
            $summary['categories'],
            Str::plural('category', $summary['categories']),
            $summary['skipped'],
        ));

        return self::SUCCESS;
    }
}
