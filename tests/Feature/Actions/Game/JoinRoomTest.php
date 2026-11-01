<?php

use App\Actions\Game\JoinRoom;
use App\Events\RoomUpdated;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\assertDatabaseCount;

it('adds the player to the room and tells the others', function () {
    $room = Room::factory()->withAdmin()->create();
    Event::fake([RoomUpdated::class]);

    $player = app(JoinRoom::class)->handle($room, '  Nikos ');

    expect($player->name)->toBe('Nikos')
        ->and($player->room_id)->toBe($room->id)
        ->and($player->is_ready)->toBeFalse();
    Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $event) => $event->code === $room->code && $event->reason === 'player-joined');
});

it('rejects a name that another player already uses regardless of case', function () {
    $room = Room::factory()->withAdmin('Maria')->create();

    expect(fn () => app(JoinRoom::class)->handle($room, 'maria'))
        ->toThrow(ValidationException::class, 'That name is already taken in this room.');

    assertDatabaseCount('players', 1);
});

it('rejects names that are too short or too long', function (string $name) {
    $room = Room::factory()->withAdmin()->create();

    expect(fn () => app(JoinRoom::class)->handle($room, $name))
        ->toThrow(ValidationException::class);
})->with([
    'one character' => ['A'],
    'twenty-one characters' => [str_repeat('a', 21)],
    'blank' => ['   '],
]);

it('rejects joining once the game has started', function () {
    $room = Room::factory()->playing()->withAdmin()->create();

    expect(fn () => app(JoinRoom::class)->handle($room, 'Latecomer'))
        ->toThrow(GameException::class, 'A game is already in progress in this room.');
});

it('rejects joining a full room', function () {
    $room = Room::factory()->create();
    Player::factory()->for($room)->count(Room::MAX_PLAYERS)->create();

    expect(fn () => app(JoinRoom::class)->handle($room, 'OneTooMany'))
        ->toThrow(GameException::class, 'This room is full.');
});
