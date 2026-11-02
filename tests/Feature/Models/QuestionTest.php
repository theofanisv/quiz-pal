<?php

use App\Models\Question;

use function Pest\Laravel\travelTo;

it('renders text and answers in the current locale', function () {
    $question = Question::factory()->create([
        'text_en' => 'What is it?',
        'text_el' => 'Τι είναι;',
        'answers_en' => ['a', 'b', 'c', 'd'],
        'answers_el' => ['α', 'β', 'γ', 'δ'],
    ]);

    app()->setLocale('el');

    expect($question->text)->toBe('Τι είναι;')
        ->and($question->answers)->toBe(['α', 'β', 'γ', 'δ']);
});

it('falls back to english for an unsupported locale', function () {
    $question = Question::factory()->create([
        'text_en' => 'What is it?',
        'answers_en' => ['a', 'b', 'c', 'd'],
    ]);

    app()->setLocale('fr');

    expect($question->text)->toBe('What is it?')
        ->and($question->answers)->toBe(['a', 'b', 'c', 'd']);
});

it('knows which choice is correct', function () {
    $question = Question::factory()->correctAt(2)->create();

    expect($question->isCorrect(2))->toBeTrue()
        ->and($question->isCorrect(0))->toBeFalse();
});

it('falls back to english when a question has no greek text', function () {
    $question = Question::factory()->englishOnly()->create([
        'text_en' => 'What is it?',
        'answers_en' => ['a', 'b', 'c', 'd'],
    ]);

    app()->setLocale('el');

    expect($question->text)->toBe('What is it?')
        ->and($question->answers)->toBe(['a', 'b', 'c', 'd']);
});

it('keeps a reported question out of rotation until a review at or after the report', function () {
    travelTo('2026-11-02 12:00:00');
    $neverReported = Question::factory()->create();
    $reported = Question::factory()->reported()->create();
    $verified = Question::factory()->verified()->create();
    $verifiedInTheSameSecond = Question::factory()->create(['reported_at' => now(), 'verified_at' => now()]);
    $reportedAgain = Question::factory()->create(['reported_at' => now(), 'verified_at' => now()->subDay()]);

    $inRotation = Question::query()->inRotation()->orderBy('id')->pluck('id')->all();
    $awaitingReview = Question::query()->awaitingReview()->orderBy('id')->pluck('id')->all();

    expect($inRotation)->toBe([$neverReported->id, $verified->id, $verifiedInTheSameSecond->id])
        ->and($awaitingReview)->toBe([$reported->id, $reportedAgain->id]);
});
