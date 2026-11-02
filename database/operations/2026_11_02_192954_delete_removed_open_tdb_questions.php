<?php

use App\Actions\Questions\DeleteRemovedOpenTdbQuestions;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

/**
 * Delete the 46 Open Trivia DB questions that were taken out of the snapshot on 2 Nov 2026
 * (wrong, out of date or with two correct answers) from databases that had imported them.
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
        app(DeleteRemovedOpenTdbQuestions::class)->handle();
    }
};
