<?php

use App\Actions\Game\SetPlayerReady;
use App\Events\RoomUpdated;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\Event;

it('marks the player ready and tells the room', function () {
    $player = Player::factory()->create();
    Event::fake([RoomUpdated::class]);

    app(SetPlayerReady::class)->handle($player, true);

    expect($player->fresh()->is_ready)->toBeTrue();
    Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $event) => $event->reason === 'ready-changed');
});

it('lets a player take back their ready state', function () {
    $player = Player::factory()->ready()->create();

    app(SetPlayerReady::class)->handle($player, false);

    expect($player->fresh()->is_ready)->toBeFalse();
});

it('rejects ready changes once the game has started', function () {
    $player = Player::factory()->for(Room::factory()->playing())->create();

    expect(fn () => app(SetPlayerReady::class)->handle($player, true))
        ->toThrow(GameException::class);

    expect($player->fresh()->is_ready)->toBeFalse();
});
