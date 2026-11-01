<?php

use App\Support\RoomCode;

it('generates four unambiguous uppercase letters', function () {
    $code = RoomCode::generate();

    expect($code)->toHaveLength(4)
        ->and(RoomCode::isValid($code))->toBeTrue();
});

it('normalizes user input to uppercase without surrounding spaces', function () {
    expect(RoomCode::normalize(' abcd '))->toBe('ABCD');
});

it('rejects codes with ambiguous or lowercase characters', function (string $code) {
    expect(RoomCode::isValid($code))->toBeFalse();
})->with([
    'letter I' => ['ABCI'],
    'letter O' => ['ABCO'],
    'digit' => ['AB12'],
    'too short' => ['ABC'],
    'lowercase' => ['abcd'],
]);
