<?php

namespace App\Console\Commands;

use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class VerifyQuestions extends Command
{
    protected $signature = 'quiz:verify-questions {ids* : Ids of reported questions that were reviewed and are correct now}';

    protected $description = 'Mark reported questions as reviewed, so new games use them again';

    public function handle(): int
    {
        $arguments = array_map(strval(...), (array) $this->argument('ids'));
        $invalid = array_filter($arguments, fn (string $id): bool => ! ctype_digit($id));

        if ($invalid !== []) {
            $this->components->error('Question ids are whole numbers, got: '.implode(', ', $invalid));

            return self::FAILURE;
        }

        $ids = array_values(array_unique(array_map(intval(...), $arguments)));
        $awaitingIds = Question::query()->whereKey($ids)->awaitingReview()->pluck('id')->all();

        foreach (array_diff($ids, $awaitingIds) as $id) {
            $this->components->warn("Question {$id} does not wait for review (unknown id, never reported or already verified).");
        }

        $verified = $awaitingIds === []
            ? 0
            : Question::query()->whereKey($awaitingIds)->update(['verified_at' => now()]);

        $this->components->info(sprintf('Verified %d %s.', $verified, Str::plural('question', $verified)));

        return self::SUCCESS;
    }
}
