<?php

use App\Models\Question;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

it('marks the given reported questions as verified', function () {
    travelTo('2026-11-02 12:00:00');
    [$first, $second] = Question::factory()->reported()->count(2)->create();
    $stillReported = Question::factory()->reported()->create();

    artisan('quiz:verify-questions', ['ids' => [(string) $first->id, (string) $second->id]])
        ->expectsOutputToContain('Verified 2 questions.')
        ->assertSuccessful();

    expect($first->fresh()->verified_at->toDateTimeString())->toBe('2026-11-02 12:00:00')
        ->and($second->fresh()->verified_at->toDateTimeString())->toBe('2026-11-02 12:00:00');
    expect(Question::query()->awaitingReview()->pluck('id')->all())->toBe([$stillReported->id]);
});

it('warns about ids that do not wait for review and leaves them alone', function () {
    $verified = Question::factory()->verified()->create();
    $verifiedAt = $verified->verified_at;
    $neverReported = Question::factory()->create();

    artisan('quiz:verify-questions', ['ids' => [(string) $verified->id, (string) $neverReported->id, '999999']])
        ->expectsOutputToContain("Question {$verified->id} does not wait for review")
        ->expectsOutputToContain("Question {$neverReported->id} does not wait for review")
        ->expectsOutputToContain('Question 999999 does not wait for review')
        ->expectsOutputToContain('Verified 0 questions.')
        ->assertSuccessful();

    expect($verified->fresh()->verified_at->equalTo($verifiedAt))->toBeTrue()
        ->and($neverReported->fresh()->verified_at)->toBeNull();
});

it('refuses ids that are not whole numbers without verifying anything', function () {
    $reported = Question::factory()->reported()->create();

    artisan('quiz:verify-questions', ['ids' => [(string) $reported->id, 'abc']])
        ->expectsOutputToContain('Question ids are whole numbers, got: abc')
        ->assertFailed();

    expect($reported->fresh()->verified_at)->toBeNull();
});
