<?php

use App\Actions\Game\RemovePlayer;
use App\Events\RoomUpdated;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertModelMissing;

it('lets the admin remove another player from the lobby', function () {
    $room = Room::factory()->withAdmin()->create();
    $player = Player::factory()->for($room)->create();
    Event::fake([RoomUpdated::class]);

    app(RemovePlayer::class)->handle($room, $room->admin, $player);

    assertModelMissing($player);
    Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $event) => $event->reason === 'player-removed');
});

it('forbids players other than the admin', function () {
    $room = Room::factory()->withAdmin()->create();
    [$actor, $target] = Player::factory()->for($room)->count(2)->create();

    expect(fn () => app(RemovePlayer::class)->handle($room, $actor, $target))
        ->toThrow(GameException::class, 'Only the room admin can do that.');

    assertModelExists($target);
});

it('refuses to remove the admin', function () {
    $room = Room::factory()->withAdmin()->create();

    expect(fn () => app(RemovePlayer::class)->handle($room, $room->admin, $room->admin))
        ->toThrow(GameException::class, 'The room admin cannot be removed.');
});

it('refuses to remove a player of another room', function () {
    $room = Room::factory()->withAdmin()->create();
    $stranger = Player::factory()->create();

    expect(fn () => app(RemovePlayer::class)->handle($room, $room->admin, $stranger))
        ->toThrow(GameException::class, 'You are not a player in this room.');

    assertModelExists($stranger);
});
