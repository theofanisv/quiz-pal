<?php

use App\Enums\PlayerColor;
use App\Models\Room;

it('has as many colours as a room has seats', function () {
    expect(PlayerColor::cases())->toHaveCount(Room::MAX_PLAYERS);
});

it('has a distinct hex for every colour', function () {
    $hexes = array_map(fn (PlayerColor $color): string => $color->hex(), PlayerColor::cases());

    expect($hexes)->each->toMatch('/^#[0-9a-f]{6}$/')
        ->and(array_unique($hexes))->toHaveCount(count($hexes));
});

it('returns the first colour nobody has taken', function () {
    expect(PlayerColor::firstFree([PlayerColor::Violet, PlayerColor::Coral]))->toBe(PlayerColor::Mint);
});

it('accepts raw values as taken colours', function () {
    expect(PlayerColor::firstFree(['violet']))->toBe(PlayerColor::Coral);
});

it('throws when every colour is taken', function () {
    expect(fn () => PlayerColor::firstFree(PlayerColor::cases()))->toThrow(LogicException::class);
});
