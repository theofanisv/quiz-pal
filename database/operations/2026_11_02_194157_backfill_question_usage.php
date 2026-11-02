<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

/**
 * Fill `times_shown` and `last_shown_at` from the rounds still in the database, so the first games after the
 * rollout already skip the questions recent games showed. Rounds of reset or pruned rooms are gone, so the
 * counts start from what is left.
 */
return new class extends OneTimeOperation
{
    /**
     * Run during the update itself: the server has no queue worker.
     */
    protected bool $async = false;

    /**
     * Process the operation.
     */
    public function process(): void
    {
        DB::table('questions')->update([
            'times_shown' => $this->startedRounds()->selectRaw('count(*)'),
            'last_shown_at' => $this->startedRounds()->selectRaw('max(rounds.started_at)'),
        ]);
    }

    /**
     * The rounds that showed the question being updated.
     */
    private function startedRounds(): Builder
    {
        return DB::table('rounds')
            ->whereColumn('rounds.question_id', 'questions.id')
            ->whereNotNull('rounds.started_at');
    }
};
