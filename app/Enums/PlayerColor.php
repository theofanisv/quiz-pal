<?php

namespace App\Enums;

use LogicException;

/**
 * The colour a player is drawn in for the whole room. There are exactly as many
 * colours as seats (Room::MAX_PLAYERS), and a room never repeats one. The first
 * eight stay clear of the four answer-tile neons so a player is never mistaken
 * for a tile.
 */
enum PlayerColor: string
{
    case Violet = 'violet';
    case Coral = 'coral';
    case Mint = 'mint';
    case Sky = 'sky';
    case Rose = 'rose';
    case Gold = 'gold';
    case Teal = 'teal';
    case Orchid = 'orchid';
    case Cyan = 'cyan';
    case Magenta = 'magenta';
    case Lime = 'lime';
    case Amber = 'amber';
    case Tangerine = 'tangerine';
    case Indigo = 'indigo';
    case Lavender = 'lavender';
    case Emerald = 'emerald';
    case Peach = 'peach';
    case Ruby = 'ruby';
    case Pearl = 'pearl';
    case Steel = 'steel';

    public function hex(): string
    {
        return match ($this) {
            self::Violet => '#9d6bff',
            self::Coral => '#ff6b4a',
            self::Mint => '#3dffc5',
            self::Sky => '#4da3ff',
            self::Rose => '#ff7aa8',
            self::Gold => '#ffe14d',
            self::Teal => '#19c9b0',
            self::Orchid => '#d86bff',
            self::Cyan => '#22e1ff',
            self::Magenta => '#ff3cac',
            self::Lime => '#b4ff39',
            self::Amber => '#ffb830',
            self::Tangerine => '#ff8c1a',
            self::Indigo => '#6c7bff',
            self::Lavender => '#c4b5ff',
            self::Emerald => '#2ee36b',
            self::Peach => '#ffc39a',
            self::Ruby => '#ff2d55',
            self::Pearl => '#e9ecff',
            self::Steel => '#8fa3bf',
        };
    }

    /**
     * The first colour, in palette order, that none of the given players use.
     *
     * @param  iterable<int, self|string|null>  $taken
     *
     * @throws LogicException when every colour is taken
     */
    public static function firstFree(iterable $taken): self
    {
        $takenValues = [];

        foreach ($taken as $color) {
            $takenValues[] = $color instanceof self ? $color->value : $color;
        }

        foreach (self::cases() as $color) {
            if (! in_array($color->value, $takenValues, true)) {
                return $color;
            }
        }

        throw new LogicException('Every player colour is already taken.');
    }
}
