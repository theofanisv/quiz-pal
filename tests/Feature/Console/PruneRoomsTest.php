<?php

use App\Models\Answer;
use App\Models\Player;
use App\Models\Room;
use App\Models\Round;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\freezeTime;

it('deletes rooms idle for longer than seven days together with their players', function () {
    freezeTime();
    $stale = Room::factory()->create(['updated_at' => now()->subDays(8)]);
    $stalePlayer = Player::factory()->for($stale)->create(['updated_at' => now()->subDays(8)]);
    $recent = Room::factory()->create(['updated_at' => now()->subDays(6)]);

    artisan('quiz:prune-rooms')
        ->expectsOutputToContain('Deleted 1 room(s)')
        ->assertSuccessful();

    assertModelMissing($stale);
    assertModelMissing($stalePlayer);
    assertModelExists($recent);
});

it('accepts a custom idle threshold in days', function () {
    freezeTime();
    $room = Room::factory()->create(['updated_at' => now()->subDays(2)]);

    artisan('quiz:prune-rooms', ['--days' => 1])->assertSuccessful();

    assertModelMissing($room);
});

it('keeps the games, rounds and answers of a pruned room without the room and its players', function () {
    freezeTime();
    $room = Room::factory()->finished()->create(['updated_at' => now()->subDays(8)]);
    $player = Player::factory()->for($room)->create();
    $round = Round::factory()->forRoom($room)->revealed()->create();
    $answer = Answer::factory()->for($round)->for($player)->create();

    artisan('quiz:prune-rooms')->assertSuccessful();

    assertModelMissing($room);
    assertModelMissing($player);
    assertModelExists($round);
    expect($round->game->fresh()->room_id)->toBeNull()
        ->and($answer->fresh()->only(['round_id', 'player_id']))->toBe(['round_id' => $round->id, 'player_id' => null]);
});
