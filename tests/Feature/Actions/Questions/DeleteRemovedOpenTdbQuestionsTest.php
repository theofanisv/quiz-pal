<?php

use App\Actions\Questions\DeleteRemovedOpenTdbQuestions;
use App\Models\Category;
use App\Models\Question;
use App\Models\Round;

use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertModelMissing;

it('deletes a listed question from its open trivia db category, together with its rounds', function () {
    useRemovedOpenTdbQuestions([['category' => 9, 'text' => 'Is this wrong?']]);
    $wrong = Question::factory()->for(Category::factory()->openTdb(9))->create(['text_en' => 'Is this wrong?']);
    $round = Round::factory()->for($wrong)->create();

    $deleted = app(DeleteRemovedOpenTdbQuestions::class)->handle();

    expect($deleted)->toBe(1);
    assertModelMissing($wrong);
    assertModelMissing($round);
});

it('keeps unlisted questions and the same text in another open trivia db category', function () {
    useRemovedOpenTdbQuestions([['category' => 9, 'text' => 'Is this wrong?']]);
    $sameCategory = Question::factory()->for(Category::factory()->openTdb(9))->create(['text_en' => 'Is this right?']);
    $otherCategory = Question::factory()->for(Category::factory()->openTdb(10))->create(['text_en' => 'Is this wrong?']);

    $deleted = app(DeleteRemovedOpenTdbQuestions::class)->handle();

    expect($deleted)->toBe(0);
    assertModelExists($sameCategory);
    assertModelExists($otherCategory);
});
