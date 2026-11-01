<?php

use App\Actions\Game\AdvanceRound;
use App\Enums\RoomStatus;
use App\Events\RoomUpdated;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Room;
use App\Models\Round;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\freezeSecond;

it('schedules the next round a few seconds ahead after the current one is revealed', function () {
    freezeSecond();
    $room = Room::factory()->playing(1)->withAdmin()->create(['total_rounds' => 2, 'seconds_per_question' => 10]);
    Round::factory()->for($room)->revealed()->create(['round_number' => 1]);
    $next = Round::factory()->for($room)->create(['round_number' => 2]);
    Event::fake([RoomUpdated::class]);

    app(AdvanceRound::class)->handle($room, $room->admin);

    expect($room->fresh()->current_round)->toBe(2)
        ->and($next->fresh()->started_at->equalTo(now()->addSeconds(3)))->toBeTrue()
        ->and($next->fresh()->ends_at->equalTo(now()->addSeconds(3 + 10)))->toBeTrue();
    Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $event) => $event->reason === 'next-round');
});

it('finishes the game after the last round is revealed', function () {
    $room = Room::factory()->playing(2)->withAdmin()->create(['total_rounds' => 2]);
    Round::factory()->for($room)->revealed()->create(['round_number' => 2]);
    Event::fake([RoomUpdated::class]);

    app(AdvanceRound::class)->handle($room, $room->admin);

    expect($room->fresh()->status)->toBe(RoomStatus::Finished);
    Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $event) => $event->reason === 'finished');
});

it('refuses to advance while the current round is still open', function () {
    $room = Room::factory()->playing(1)->withAdmin()->create();
    Round::factory()->for($room)->started()->create(['round_number' => 1]);

    expect(fn () => app(AdvanceRound::class)->handle($room, $room->admin))
        ->toThrow(GameException::class, 'Wait until the answers are revealed.');

    expect($room->fresh()->current_round)->toBe(1);
});

it('forbids players other than the admin', function () {
    $room = Room::factory()->playing(1)->withAdmin()->create();
    Round::factory()->for($room)->revealed()->create(['round_number' => 1]);
    $player = Player::factory()->for($room)->create();

    expect(fn () => app(AdvanceRound::class)->handle($room, $player))
        ->toThrow(GameException::class, 'Only the room admin can do that.');
});

it('refuses when no game is in progress', function () {
    $room = Room::factory()->withAdmin()->create();

    expect(fn () => app(AdvanceRound::class)->handle($room, $room->admin))
        ->toThrow(GameException::class, 'There is no game in progress.');
});
