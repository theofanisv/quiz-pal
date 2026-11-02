<?php

use App\Models\Category;
use App\Models\Question;

use function Pest\Laravel\artisan;

it('lists per category how often games showed the questions they may use', function () {
    $science = Category::factory()->create(['slug' => 'science']);
    $film = Category::factory()->openTdb(11)->create();
    Question::factory()->for($science)->count(2)->create();
    Question::factory()->for($science)->shown(1)->create();
    Question::factory()->for($science)->shown(5)->create();
    Question::factory()->for($science)->reported()->shown(9)->create();
    Question::factory()->for($film)->shown(2)->count(2)->create();

    artisan('quiz:question-usage')
        ->expectsTable(
            ['Category', 'Source', 'Questions', 'Never shown', 'Fewest', 'Average', 'Most'],
            [
                ['science', 'custom', 4, 2, 0, '1.5', 5],
                ['opentdb-11', 'opentdb', 2, 0, 2, '2.0', 2],
            ],
        )
        ->expectsOutputToContain('6 questions in rotation, 2 never shown. Each was shown 0 to 5 times.')
        ->assertSuccessful();
});

it('says so when no question is in rotation', function () {
    Question::factory()->reported()->create();

    artisan('quiz:question-usage')
        ->expectsOutputToContain('No questions are in rotation.')
        ->assertSuccessful();
});
