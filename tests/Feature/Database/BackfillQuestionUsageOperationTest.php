<?php

use App\Models\Question;
use App\Models\Round;

use function Pest\Laravel\artisan;

it('counts the rounds that showed each question when composer update-app runs the pending operations', function () {
    $shown = Question::factory()->create();
    Round::factory()->for($shown)->create(['started_at' => '2026-11-01 20:00:00']);
    Round::factory()->for($shown)->create(['started_at' => '2026-11-02 09:30:00']);
    Round::factory()->for($shown)->create();
    $unseen = Question::factory()->create();

    artisan('operations:process', ['--sync' => true])->assertSuccessful();

    $shown->refresh();
    expect($shown->times_shown)->toBe(2)
        ->and($shown->last_shown_at?->toDateTimeString())->toBe('2026-11-02 09:30:00');
    $unseen->refresh();
    expect($unseen->times_shown)->toBe(0)
        ->and($unseen->last_shown_at)->toBeNull();
});
