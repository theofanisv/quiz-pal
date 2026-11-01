<?php

namespace App\Console\Commands;

use App\Actions\Questions\ImportQuestions as ImportQuestionsAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportQuestions extends Command
{
    protected $signature = 'quiz:import-questions
                            {path? : A JSON file or a directory of JSON files (defaults to database/data/questions)}';

    protected $description = 'Import or update quiz questions from JSON files';

    public function handle(ImportQuestionsAction $importQuestions): int
    {
        $path = $this->argument('path') ?? database_path('data/questions');

        try {
            $result = is_dir($path)
                ? $importQuestions->handleDirectory($path)
                : ['files' => 1, 'questions' => $importQuestions->handleFile($path)];
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Imported {$result['questions']} questions from {$result['files']} file(s).");

        return self::SUCCESS;
    }
}
