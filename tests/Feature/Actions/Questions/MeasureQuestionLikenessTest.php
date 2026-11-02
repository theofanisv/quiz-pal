<?php

use App\Actions\Questions\MeasureQuestionLikeness;
use App\Models\Question;

it('rates a question higher when the group stayed on its answer longer than usual for that game', function () {
    $lingered = Question::factory()->correctAt(0)->create();
    $usual = Question::factory()->correctAt(0)->create();
    playRecordedGame([
        ['question' => $lingered, 'pause' => 30],
        ['question' => $usual],
        [],
        [],
    ]);

    $likeness = app(MeasureQuestionLikeness::class)->handle();

    expect($likeness[$lingered->id]['talk'])->toEqualWithDelta(3.0, 0.001)
        ->and($likeness[$usual->id]['talk'])->toEqualWithDelta(1.0, 0.001)
        ->and($likeness[$lingered->id]['score'])->toBeGreaterThan($likeness[$usual->id]['score']);
});

it('counts a pause longer than two minutes as two minutes, since the admin probably stepped away', function () {
    $question = Question::factory()->correctAt(0)->create();
    playRecordedGame([
        ['question' => $question, 'pause' => 600],
        [],
        [],
        [],
    ]);

    $likeness = app(MeasureQuestionLikeness::class)->handle();

    expect($likeness[$question->id]['talk'])->toEqualWithDelta(12.0, 0.001);
});

it('takes no talk time from a pause that does not show a group lingering', function (Closure $play) {
    $question = Question::factory()->correctAt(0)->create();
    $play($question);

    $likeness = app(MeasureQuestionLikeness::class)->handle();

    expect($likeness[$question->id]['talk'])->toBeNull();
})->with([
    'only one player answered' => [fn (Question $question) => playRecordedGame([
        ['question' => $question, 'pause' => 30, 'answers' => [[0, 750]]],
        [],
        [],
        [],
    ])],
    'the game was abandoned after it' => [fn (Question $question) => playRecordedGame([
        [],
        [],
        [],
        ['question' => $question, 'pause' => 30],
    ], finished: false)],
    'the game finished at the moment of the reveal, as in games recorded before the history' => [fn (Question $question) => playRecordedGame([
        [],
        [],
        [],
        ['question' => $question, 'pause' => 0],
    ])],
    'the game had fewer than three pauses to compare' => [fn (Question $question) => playRecordedGame([
        ['question' => $question, 'pause' => 30],
        [],
    ])],
]);

it('rates a question lower when players let the time run out on it', function () {
    $timedOut = Question::factory()->correctAt(0)->create();
    $answered = Question::factory()->correctAt(0)->create();
    $everyoneAnswered = [[0, 750], [0, 750], [0, 750], [0, 750]];
    playRecordedGame([
        ['question' => $timedOut, 'present' => 4, 'answers' => [[0, 750], [0, 750]]],
        ['question' => $answered, 'answers' => $everyoneAnswered],
        ['answers' => $everyoneAnswered],
        ['answers' => $everyoneAnswered],
    ]);

    $likeness = app(MeasureQuestionLikeness::class)->handle();

    expect($likeness[$timedOut->id]['participation'])->toEqualWithDelta(0.5, 0.001)
        ->and($likeness[$answered->id]['participation'])->toEqualWithDelta(1.0, 0.001)
        ->and($likeness[$timedOut->id]['score'])->toBeLessThan($likeness[$answered->id]['score']);
});

it('leaves participation unknown for rounds closed before the players present were recorded', function () {
    $question = Question::factory()->correctAt(0)->create();
    playRecordedGame([['question' => $question, 'present' => null]]);

    $likeness = app(MeasureQuestionLikeness::class)->handle();

    expect($likeness[$question->id]['participation'])->toBeNull();
});

it('rates a question everyone gets right instantly below one that takes effort', function () {
    $instant = Question::factory()->correctAt(0)->create();
    $effort = Question::factory()->correctAt(0)->create();
    playRecordedGame([
        ['question' => $instant, 'answers' => [[0, 1000], [0, 1000]]],
        ['question' => $effort, 'answers' => [[0, 1000], [1, 0]]],
        [],
        [],
    ]);

    $likeness = app(MeasureQuestionLikeness::class)->handle();

    expect($likeness[$instant->id])->toMatchArray(['correct_rate' => 1.0, 'ease' => 1.0])
        ->and($likeness[$effort->id])->toMatchArray(['correct_rate' => 0.5, 'ease' => 0.5])
        ->and($likeness[$instant->id]['score'])->toBeLessThan($likeness[$effort->id]['score']);
});

it('measures how much the wrong answers cluster on one option', function () {
    $gotcha = Question::factory()->correctAt(0)->create();
    $evenlySpread = Question::factory()->correctAt(0)->create();
    $oneWrong = Question::factory()->correctAt(0)->create();
    playRecordedGame([
        ['question' => $gotcha, 'answers' => [[1, 0], [1, 0], [1, 0]]],
        ['question' => $evenlySpread, 'answers' => [[1, 0], [2, 0], [3, 0]]],
        ['question' => $oneWrong, 'answers' => [[0, 750], [2, 0]]],
    ]);

    $likeness = app(MeasureQuestionLikeness::class)->handle();

    expect($likeness[$gotcha->id]['wrong_spread'])->toEqualWithDelta(1.0, 0.001)
        ->and($likeness[$evenlySpread->id]['wrong_spread'])->toEqualWithDelta(0.0, 0.001)
        ->and($likeness[$oneWrong->id]['wrong_spread'])->toBeNull();
});

it('trusts the signals of a question more the more often it was played', function () {
    $playedOnce = Question::factory()->correctAt(0)->create();
    $playedOften = Question::factory()->correctAt(0)->create();
    playRecordedGame([['question' => $playedOnce, 'pause' => 30], [], [], []]);
    foreach (range(1, 4) as $game) {
        playRecordedGame([['question' => $playedOften, 'pause' => 30], [], [], []]);
    }

    $likeness = app(MeasureQuestionLikeness::class)->handle();

    expect($likeness[$playedOnce->id]['plays'])->toBe(1)
        ->and($likeness[$playedOften->id]['plays'])->toBe(4)
        ->and($likeness[$playedOften->id]['score'])->toBeGreaterThan($likeness[$playedOnce->id]['score']);
});

it('counts a play only once the round was revealed', function () {
    $question = Question::factory()->correctAt(0)->create();
    $neverRevealed = Question::factory()->correctAt(0)->create();
    playRecordedGame([['question' => $question]]);
    playRecordedGame([['question' => $question, 'revealed' => false]], finished: false);
    playRecordedGame([['question' => $neverRevealed, 'revealed' => false]], finished: false);

    $likeness = app(MeasureQuestionLikeness::class)->handle();

    expect($likeness[$question->id]['plays'])->toBe(1)
        ->and($likeness->has($neverRevealed->id))->toBeFalse();
});

it('lists the most liked questions first', function () {
    $liked = Question::factory()->correctAt(0)->create();
    $disliked = Question::factory()->correctAt(0)->create();
    playRecordedGame([
        ['question' => $disliked, 'pause' => 5],
        ['question' => $liked, 'pause' => 30],
        [],
        [],
    ]);

    $likeness = app(MeasureQuestionLikeness::class)->handle();

    expect($likeness->keys()->first())->toBe($liked->id)
        ->and($likeness->keys()->last())->toBe($disliked->id);
});
