<?php

namespace App\Actions\Game;

use App\Models\Round;

class CloseExpiredRound
{
    public function __construct(private RevealRound $revealRound) {}

    /**
     * Reveal the round when its timer has run out. Any client may call this;
     * the server clock decides.
     */
    public function handle(Round $round): bool
    {
        if (! $round->isOpen() || ! $round->hasExpired()) {
            return false;
        }

        return $this->revealRound->handle($round);
    }
}
