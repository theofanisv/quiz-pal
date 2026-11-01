<?php

use App\Actions\Game\RevealRound;
use App\Models\Answer;
use App\Models\Player;
use App\Models\Room;
use App\Models\Round;

it('adds each answer\'s points to its player only once', function () {
    $room = Room::factory()->playing()->withAdmin()->create();
    $round = Round::factory()->for($room)->started()->create();
    $player = Player::factory()->for($room)->create(['score' => 100]);
    Answer::factory()->for($round)->for($player)->create(['is_correct' => true, 'points' => 750]);
    Answer::factory()->for($round)->for($room->admin)->create(['is_correct' => false, 'points' => 0]);

    $first = app(RevealRound::class)->handle($round);
    $second = app(RevealRound::class)->handle($round->fresh());

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($round->fresh()->isRevealed())->toBeTrue()
        ->and($player->fresh()->score)->toBe(850)
        ->and($room->admin->fresh()->score)->toBe(0);
});
