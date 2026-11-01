<?php

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case Greek = 'el';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Greek => 'Ελληνικά',
        };
    }
}
