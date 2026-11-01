<?php

use App\Actions\Game\TrackPresence;
use App\Models\Answer;
use App\Models\Player;
use App\Models\Room;
use App\Models\Round;

it('marks the listed players connected and everyone else disconnected', function () {
    $room = Room::factory()->create();
    [$here, $gone] = Player::factory()->for($room)->count(2)->create();
    $gone->update(['is_connected' => true]);

    app(TrackPresence::class)->sync($room, [$here->id]);

    expect($here->fresh()->is_connected)->toBeTrue()
        ->and($gone->fresh()->is_connected)->toBeFalse();
});

it('marks a joining player connected again', function () {
    $player = Player::factory()->disconnected()->create();

    app(TrackPresence::class)->joined($player->room, $player->id);

    expect($player->fresh()->is_connected)->toBeTrue();
});

it('reveals the open round when the leaving player was the only one still to answer', function () {
    $room = Room::factory()->playing()->withAdmin()->create();
    $round = Round::factory()->for($room)->started()->create();
    $leaver = Player::factory()->for($room)->create();
    Answer::factory()->for($round)->for($room->admin)->create(['is_correct' => true, 'points' => 600]);

    app(TrackPresence::class)->left($room, $leaver->id);

    expect($leaver->fresh()->is_connected)->toBeFalse()
        ->and($round->fresh()->isRevealed())->toBeTrue()
        ->and($room->admin->fresh()->score)->toBe(600);
});

it('keeps the round open when the leaving player was not the last one pending', function () {
    $room = Room::factory()->playing()->withAdmin()->create();
    $round = Round::factory()->for($room)->started()->create();
    $leaver = Player::factory()->for($room)->create();
    Player::factory()->for($room)->create();

    app(TrackPresence::class)->left($room, $leaver->id);

    expect($round->fresh()->isRevealed())->toBeFalse();
});
