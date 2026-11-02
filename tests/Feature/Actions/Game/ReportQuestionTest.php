<?php

use App\Actions\Game\ReportQuestion;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Question;
use App\Models\Room;
use App\Models\Round;

use function Pest\Laravel\travelTo;

it('flags the question of a round on screen as waiting for review', function (string $roundState) {
    travelTo('2026-11-02 12:00:00');
    $room = Room::factory()->playing()->withAdmin()->create();
    $round = Round::factory()->forRoom($room)->{$roundState}()->create();

    $question = app(ReportQuestion::class)->handle($round, $room->admin);

    expect($question->id)->toBe($round->question_id)
        ->and($question->fresh()->reported_at->toDateTimeString())->toBe('2026-11-02 12:00:00');
    expect(Question::query()->awaitingReview()->pluck('id')->all())->toBe([$round->question_id]);
})->with([
    'while players answer' => 'started',
    'after the reveal' => 'revealed',
]);

it('reopens a question that was reviewed before', function () {
    $room = Room::factory()->playing()->withAdmin()->create();
    $question = Question::factory()->verified()->create();
    $round = Round::factory()->forRoom($room)->for($question)->started()->create();

    app(ReportQuestion::class)->handle($round, $room->admin);

    expect(Question::query()->awaitingReview()->pluck('id')->all())->toBe([$question->id]);
});

it('rejects a report from a player of another room', function () {
    $room = Room::factory()->playing()->withAdmin()->create();
    $round = Round::factory()->forRoom($room)->started()->create();
    $stranger = Player::factory()->create();

    expect(fn () => app(ReportQuestion::class)->handle($round, $stranger))
        ->toThrow(GameException::class, 'You are not a player in this room.');

    expect($round->question->fresh()->reported_at)->toBeNull();
});

it('rejects a report before the question is shown', function () {
    $room = Room::factory()->playing()->withAdmin()->create();
    $round = Round::factory()->forRoom($room)->pending()->create();

    expect(fn () => app(ReportQuestion::class)->handle($round, $room->admin))
        ->toThrow(GameException::class, 'The question has not been shown yet.');

    expect($round->question->fresh()->reported_at)->toBeNull();
});
