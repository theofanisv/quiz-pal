<?php

use App\Actions\Game\SubmitAnswer;
use App\Events\PlayerAnswered;
use App\Events\RoomUpdated;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Question;
use App\Models\Room;
use App\Models\Round;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\freezeSecond;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\travel;

/**
 * A room in play with an open 20-second round whose correct answer is index 2.
 *
 * @return array{0: Room, 1: Round}
 */
function openRound(): array
{
    $room = Room::factory()->playing()->withAdmin()->create(['seconds_per_question' => 20]);
    $round = Round::factory()->forRoom($room)->for(Question::factory()->correctAt(2))->started(20)->create();

    return [$room, $round];
}

it('scores a correct answer by how fast it came', function () {
    freezeTime();
    [$room, $round] = openRound();
    Player::factory()->for($room)->create();
    travel(5)->seconds();

    $answer = app(SubmitAnswer::class)->handle($round, $room->admin, 2);

    expect($answer->is_correct)->toBeTrue()
        ->and($answer->points)->toBe(875)
        ->and($answer->choice)->toBe(2);
});

it('records how long the player took to answer', function () {
    freezeSecond();
    [$room, $round] = openRound();
    Player::factory()->for($room)->create();
    travel(5)->seconds();

    $answer = app(SubmitAnswer::class)->handle($round, $room->admin, 2);

    expect($answer->elapsed_ms)->toBe(5000)
        ->and($answer->fresh()->elapsed_ms)->toBe(5000);
});

it('gives no points for a wrong answer', function () {
    freezeTime();
    [$room, $round] = openRound();
    Player::factory()->for($room)->create();

    $answer = app(SubmitAnswer::class)->handle($round, $room->admin, 0);

    expect($answer->is_correct)->toBeFalse()
        ->and($answer->points)->toBe(0);
});

it('keeps the first answer when a player answers twice', function () {
    freezeTime();
    [$room, $round] = openRound();
    Player::factory()->for($room)->create();
    $first = app(SubmitAnswer::class)->handle($round, $room->admin, 0);

    $second = app(SubmitAnswer::class)->handle($round, $room->admin, 2);

    expect($second->id)->toBe($first->id)
        ->and($second->choice)->toBe(0)
        ->and($round->answers()->count())->toBe(1);
});

it('tells the room who answered but keeps the round open while someone has not answered', function () {
    [$room, $round] = openRound();
    Player::factory()->for($room)->create();
    Event::fake([PlayerAnswered::class, RoomUpdated::class]);

    app(SubmitAnswer::class)->handle($round, $room->admin, 2);

    expect($round->fresh()->isRevealed())->toBeFalse()
        ->and($room->admin->fresh()->score)->toBe(0);
    Event::assertDispatched(PlayerAnswered::class, fn (PlayerAnswered $event) => $event->broadcastOn()[0]->name === "presence-room.{$room->code}"
        && $event->broadcastWith() === ['round_id' => $round->id, 'player_id' => $room->admin->id]);
    Event::assertNotDispatched(RoomUpdated::class);
});

it('reveals the round and adds the points once every connected player has answered', function () {
    freezeTime();
    [$room, $round] = openRound();
    $other = Player::factory()->for($room)->create();
    Player::factory()->for($room)->disconnected()->create();
    app(SubmitAnswer::class)->handle($round, $room->admin, 2);
    Event::fake([RoomUpdated::class]);

    app(SubmitAnswer::class)->handle($round, $other, 1);

    expect($round->fresh()->isRevealed())->toBeTrue()
        ->and($room->admin->fresh()->score)->toBe(1000)
        ->and($other->fresh()->score)->toBe(0);
    Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $event) => $event->reason === 'revealed');
});

it('rejects an answer after the timer ran out and reveals the round instead', function () {
    freezeTime();
    [$room, $round] = openRound();
    travel(20)->seconds();

    expect(fn () => app(SubmitAnswer::class)->handle($round, $room->admin, 2))
        ->toThrow(GameException::class, 'This round is already closed.');

    expect($round->fresh()->isRevealed())->toBeTrue()
        ->and($round->answers()->count())->toBe(0);
});

it('rejects an answer once the round is revealed', function () {
    $room = Room::factory()->playing()->withAdmin()->create();
    $round = Round::factory()->forRoom($room)->revealed()->create();

    expect(fn () => app(SubmitAnswer::class)->handle($round, $room->admin, 1))
        ->toThrow(GameException::class, 'This round is already closed.');
});

it('rejects an answer from a player of another room', function () {
    [, $round] = openRound();
    $stranger = Player::factory()->create();

    expect(fn () => app(SubmitAnswer::class)->handle($round, $stranger, 1))
        ->toThrow(GameException::class, 'You are not a player in this room.');
});

it('rejects a choice outside the four answers', function () {
    [$room, $round] = openRound();

    expect(fn () => app(SubmitAnswer::class)->handle($round, $room->admin, 4))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an answer before the question is shown', function () {
    freezeTime();
    $room = Room::factory()->playing()->withAdmin()->create();
    $round = Round::factory()->forRoom($room)->pending()->create();

    expect(fn () => app(SubmitAnswer::class)->handle($round, $room->admin, 1))
        ->toThrow(GameException::class, 'The question has not been shown yet.');

    expect($round->answers()->count())->toBe(0);
});

it('accepts an answer once the start moment has passed', function () {
    freezeTime();
    $room = Room::factory()->playing()->withAdmin()->create(['seconds_per_question' => 20]);
    $round = Round::factory()->forRoom($room)->for(Question::factory()->correctAt(1))->pending()->create();
    travel(3)->seconds();

    $answer = app(SubmitAnswer::class)->handle($round, $room->admin, 1);

    expect($answer->points)->toBe(1000);
});
