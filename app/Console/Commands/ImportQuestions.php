<?php

namespace App\Console\Commands;

use App\Actions\Questions\ImportQuestions as ImportQuestionsAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportQuestions extends Command
{
    protected $signature = 'quiz:import-questions
                            {path? : A JSON file or a directory of JSON files (defaults to the bundled database/data/questions and database/data/opentdb)}';

    protected $description = 'Import or update quiz questions from JSON files';

    public function handle(ImportQuestionsAction $importQuestions): int
    {
        $path = $this->argument('path');
        $path = is_string($path) ? $path : null;

        try {
            $result = match (true) {
                $path === null => $importQuestions->handleBundled(),
                is_dir($path) => $importQuestions->handleDirectory($path),
                default => ['files' => 1, 'questions' => $importQuestions->handleFile($path)],
            };
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Imported {$result['questions']} questions from {$result['files']} file(s).");

        return self::SUCCESS;
    }
}
