<?php

namespace App\Support;

class RoomCode
{
    public const int LENGTH = 4;

    /**
     * Letters that cannot be confused with each other or with digits when read aloud or typed.
     */
    public const string ALPHABET = 'ABCDEFGHJKLMNPRSTUVWXYZ';

    public static function generate(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    public static function normalize(string $input): string
    {
        return strtoupper(trim($input));
    }

    public static function isValid(string $code): bool
    {
        return preg_match('/^['.self::ALPHABET.']{'.self::LENGTH.'}$/', $code) === 1;
    }
}
