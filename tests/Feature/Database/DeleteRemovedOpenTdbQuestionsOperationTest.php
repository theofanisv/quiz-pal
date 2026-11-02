<?php

use App\Models\Category;
use App\Models\Question;
use App\Support\RemovedOpenTdbQuestions;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertModelMissing;

it('deletes the removed questions when composer update-app runs the pending operations', function () {
    $listed = app(RemovedOpenTdbQuestions::class)->all();
    $externalId = array_key_first($listed);
    $wrong = Question::factory()->for(Category::factory()->openTdb($externalId))->create(['text_en' => $listed[$externalId][0]]);

    artisan('operations:process', ['--sync' => true])->assertSuccessful();

    assertModelMissing($wrong);
});
