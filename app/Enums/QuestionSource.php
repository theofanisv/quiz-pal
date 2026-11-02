<?php

namespace App\Enums;

/**
 * Where a room draws its questions from: the bilingual bank bundled with the app,
 * or the English questions mirrored from Open Trivia DB by `quiz:sync-opentdb`.
 */
enum QuestionSource: string
{
    case Custom = 'custom';
    case OpenTdb = 'opentdb';

    public function label(): string
    {
        return match ($this) {
            self::Custom => __('Built-in questions'),
            self::OpenTdb => __('Open Trivia DB'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Custom => __('Bilingual questions written for QuizPal.'),
            self::OpenTdb => __('Community questions from Open Trivia DB; the ones not translated yet show in English.'),
        };
    }
}
