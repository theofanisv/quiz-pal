<?php

use App\Models\Question;
use App\Models\Round;

use function Pest\Laravel\freezeSecond;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\travel;

it('shows the answers in the shuffled order keyed by their original index', function () {
    $question = Question::factory()->create(['answers_en' => ['a', 'b', 'c', 'd']]);
    $round = Round::factory()->for($question)->create(['answer_order' => [2, 0, 3, 1]]);

    app()->setLocale('en');

    expect($round->displayedAnswers())->toBe([2 => 'c', 0 => 'a', 3 => 'd', 1 => 'b']);
});

it('reports whole seconds left until the round times out', function () {
    freezeTime();
    $round = Round::factory()->started(20)->create();

    travel(7)->seconds();

    expect($round->secondsLeft())->toBe(13)
        ->and($round->hasExpired())->toBeFalse();
});

it('has expired once the end time passes', function () {
    freezeTime();
    $round = Round::factory()->started(20)->create();

    travel(20)->seconds();

    expect($round->hasExpired())->toBeTrue()
        ->and($round->secondsLeft())->toBe(0);
});

it('is open only between start and reveal', function () {
    expect(Round::factory()->create()->isOpen())->toBeFalse()
        ->and(Round::factory()->started()->create()->isOpen())->toBeTrue()
        ->and(Round::factory()->revealed()->create()->isOpen())->toBeFalse();
});

it('is pending until the scheduled start moment, then open', function () {
    freezeTime();
    $round = Round::factory()->pending(20)->create();

    expect($round->isPending())->toBeTrue()
        ->and($round->isOpen())->toBeFalse()
        ->and($round->secondsUntilStart())->toBe(3);

    travel(3)->seconds();

    expect($round->isPending())->toBeFalse()
        ->and($round->hasStarted())->toBeTrue()
        ->and($round->isOpen())->toBeTrue()
        ->and($round->secondsUntilStart())->toBe(0)
        ->and($round->secondsLeft())->toBe(20);
});

it('holds the move to the next round for two seconds after the reveal', function () {
    freezeSecond();
    $round = Round::factory()->started()->create();

    expect($round->millisecondsUntilAdvance())->toBe(0);

    $round->update(['revealed_at' => now()]);

    expect($round->millisecondsUntilAdvance())->toBe(2000);

    travel(1500)->milliseconds();

    expect($round->millisecondsUntilAdvance())->toBe(500);

    travel(1)->seconds();

    expect($round->millisecondsUntilAdvance())->toBe(0);
});
