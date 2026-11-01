<?php

namespace App\Support;

class Scoring
{
    public const int MAX_POINTS = 1000;

    public const int MIN_CORRECT_POINTS = 500;

    /**
     * Points for an answer: nothing when wrong, otherwise a linear speed bonus
     * from 1000 (instant) down to 500 (at the buzzer).
     */
    public static function points(bool $isCorrect, int $elapsedMs, int $limitMs): int
    {
        if (! $isCorrect) {
            return 0;
        }

        if ($limitMs <= 0) {
            return self::MAX_POINTS;
        }

        $fraction = min(1.0, max(0.0, $elapsedMs / $limitMs));

        $points = (int) round(self::MAX_POINTS * (1 - 0.5 * $fraction));

        return max(self::MIN_CORRECT_POINTS, $points);
    }
}
