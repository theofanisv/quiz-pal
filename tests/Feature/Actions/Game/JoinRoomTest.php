<?php

use App\Actions\Game\JoinRoom;
use App\Enums\PlayerColor;
use App\Events\RoomUpdated;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
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

it('gives each player the first free colour of the room', function () {
    $room = Room::factory()->withAdmin()->create();

    $player = app(JoinRoom::class)->handle($room, 'Nikos');

    expect($room->admin->color)->toBe(PlayerColor::Violet)
        ->and($player->color)->toBe(PlayerColor::Coral);
});

it('gives the twentieth player the last colour', function () {
    $room = Room::factory()->create();
    Player::factory()->for($room)->count(Room::MAX_PLAYERS - 1)->create();

    $player = app(JoinRoom::class)->handle($room, 'Last');

    expect($player->color)->toBe(PlayerColor::Steel)
        ->and($room->players()->pluck('color')->unique())->toHaveCount(Room::MAX_PLAYERS);
});

it('reuses the colour of a removed player', function () {
    $room = Room::factory()->withAdmin()->create();
    $nikos = app(JoinRoom::class)->handle($room, 'Nikos');
    app(JoinRoom::class)->handle($room, 'Anna');

    $nikos->delete();
    $petros = app(JoinRoom::class)->handle($room, 'Petros');

    expect($petros->color)->toBe(PlayerColor::Coral);
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

it('keeps the new player and reports the failure when the broadcast cannot be delivered', function () {
    $room = Room::factory()->withAdmin()->create();
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => '1',
            'options' => ['host' => '127.0.0.1', 'port' => 1, 'scheme' => 'http', 'useTLS' => false],
        ],
    ]);
    Exceptions::fake();

    $player = app(JoinRoom::class)->handle($room, 'Nikos');

    expect($player->exists)->toBeTrue()
        ->and($room->players()->count())->toBe(2);
    Exceptions::assertReported(BroadcastException::class);
});
