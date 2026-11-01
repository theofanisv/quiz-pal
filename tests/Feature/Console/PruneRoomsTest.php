<?php

use App\Models\Player;
use App\Models\Room;

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
