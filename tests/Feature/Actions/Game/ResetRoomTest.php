<?php

use App\Actions\Game\ResetRoom;
use App\Enums\RoomStatus;
use App\Events\RoomUpdated;
use App\Exceptions\GameException;
use App\Models\Answer;
use App\Models\Player;
use App\Models\Room;
use App\Models\Round;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\assertModelExists;

it('sends a finished room back to the lobby with the same players', function () {
    $room = Room::factory()->finished()->withAdmin()->create(['total_rounds' => 1]);
    $player = Player::factory()->for($room)->ready()->create(['score' => 900]);
    $round = Round::factory()->forRoom($room)->revealed()->create();
    $answer = Answer::factory()->for($round)->for($player)->create();
    Event::fake([RoomUpdated::class]);

    app(ResetRoom::class)->handle($room, $room->admin);

    $room->refresh();
    expect($room->status)->toBe(RoomStatus::Lobby)
        ->and($room->current_round)->toBe(0)
        ->and($room->players)->toHaveCount(2)
        ->and($player->fresh()->score)->toBe(0)
        ->and($player->fresh()->is_ready)->toBeFalse()
        ->and($player->fresh()->color)->toBe($player->color);
    assertModelExists($round);
    assertModelExists($answer);
    Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $event) => $event->reason === 'reset');
});

it('refuses while a game is still in progress', function () {
    $room = Room::factory()->playing()->withAdmin()->create();

    expect(fn () => app(ResetRoom::class)->handle($room, $room->admin))
        ->toThrow(GameException::class, 'The game has not finished yet.');
});

it('forbids players other than the admin', function () {
    $room = Room::factory()->finished()->withAdmin()->create();
    $player = Player::factory()->for($room)->create();

    expect(fn () => app(ResetRoom::class)->handle($room, $player))
        ->toThrow(GameException::class, 'Only the room admin can do that.');

    expect($room->fresh()->status)->toBe(RoomStatus::Finished);
});
