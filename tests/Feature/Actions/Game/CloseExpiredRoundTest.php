<?php

use App\Actions\Game\CloseExpiredRound;
use App\Models\Room;
use App\Models\Round;

use function Pest\Laravel\freezeTime;
use function Pest\Laravel\travel;

it('leaves the round open while time remains', function () {
    freezeTime();
    $round = Round::factory()->for(Room::factory()->playing())->started(20)->create();
    travel(19)->seconds();

    $closed = app(CloseExpiredRound::class)->handle($round);

    expect($closed)->toBeFalse()
        ->and($round->fresh()->isRevealed())->toBeFalse();
});

it('reveals the round once the timer has run out', function () {
    freezeTime();
    $round = Round::factory()->for(Room::factory()->playing())->started(20)->create();
    travel(20)->seconds();

    $closed = app(CloseExpiredRound::class)->handle($round);

    expect($closed)->toBeTrue()
        ->and($round->fresh()->isRevealed())->toBeTrue();
});
