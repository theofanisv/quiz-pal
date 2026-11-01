<?php

use App\Models\Question;
use App\Models\Round;

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
