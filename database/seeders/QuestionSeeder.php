<?php

namespace Database\Seeders;

use App\Actions\Questions\ImportQuestions;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    public function run(ImportQuestions $importQuestions): void
    {
        $result = $importQuestions->handleDirectory(database_path('data/questions'));

        $this->command?->info("Imported {$result['questions']} questions from {$result['files']} category files.");
    }
}
