<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A game rule was violated. The message is translated and safe to show to the player.
 */
class GameException extends RuntimeException
{
    public static function roomNotInLobby(): self
    {
        return new self(__('This room is no longer accepting changes.'));
    }

    public static function gameInProgress(): self
    {
        return new self(__('A game is already in progress in this room.'));
    }

    public static function roomFull(): self
    {
        return new self(__('This room is full.'));
    }

    public static function notAdmin(): self
    {
        return new self(__('Only the room admin can do that.'));
    }

    public static function liveUpdatesUnavailable(): self
    {
        return new self(__('Live updates are unavailable, so the game cannot start right now.'));
    }

    public static function notEveryoneReady(): self
    {
        return new self(__('Everyone has to be ready before the game can start.'));
    }

    public static function notEnoughQuestions(int $available): self
    {
        return new self(__('Only :count questions are available for the chosen categories. Choose more categories or fewer rounds.', ['count' => $available]));
    }

    public static function roundNotStarted(): self
    {
        return new self(__('The question has not been shown yet.'));
    }

    public static function roundClosed(): self
    {
        return new self(__('This round is already closed.'));
    }

    public static function roundStillOpen(): self
    {
        return new self(__('Wait until the answers are revealed.'));
    }

    public static function notPlaying(): self
    {
        return new self(__('There is no game in progress.'));
    }

    public static function notFinished(): self
    {
        return new self(__('The game has not finished yet.'));
    }

    public static function cannotRemoveAdmin(): self
    {
        return new self(__('The room admin cannot be removed.'));
    }

    public static function notInRoom(): self
    {
        return new self(__('You are not a player in this room.'));
    }
}
