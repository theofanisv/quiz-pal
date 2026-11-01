<?php

use App\Models\Player;
use App\Models\Room;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\freezeTime;

it('deletes rooms idle for longer than the given hours together with their players', function () {
    freezeTime();
    $stale = Room::factory()->create(['updated_at' => now()->subHours(25)]);
    $stalePlayer = Player::factory()->for($stale)->create(['updated_at' => now()->subHours(25)]);
    $recent = Room::factory()->create(['updated_at' => now()->subHours(23)]);

    artisan('quiz:prune-rooms')
        ->expectsOutputToContain('Deleted 1 room(s)')
        ->assertSuccessful();

    assertModelMissing($stale);
    assertModelMissing($stalePlayer);
    assertModelExists($recent);
});

it('accepts a custom idle threshold', function () {
    freezeTime();
    $room = Room::factory()->create(['updated_at' => now()->subHours(2)]);

    artisan('quiz:prune-rooms', ['--hours' => 1])->assertSuccessful();

    assertModelMissing($room);
});
