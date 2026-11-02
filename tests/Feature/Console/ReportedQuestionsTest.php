<?php

use App\Models\Category;
use App\Models\Question;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

it('lists only the questions that wait for review', function () {
    travelTo('2026-11-02 12:00:00');
    $category = Category::factory()->create(['slug' => 'science']);
    $reported = Question::factory()->for($category)->reported()->correctAt(1)->create([
        'text_en' => 'Which planet is known as the Red Planet?',
        'answers_en' => ['Venus', 'Mars', 'Jupiter', 'Saturn'],
    ]);
    Question::factory()->verified()->create(['text_en' => 'A reviewed question?']);
    Question::factory()->create(['text_en' => 'A question nobody reported?']);

    artisan('quiz:reported-questions')
        ->expectsTable(
            ['ID', 'Category', 'Reported at', 'Question', 'Correct answer'],
            [[$reported->id, 'science', '2026-11-02 11:00:00', 'Which planet is known as the Red Planet?', 'Mars']],
        )
        ->expectsOutputToContain('1 question waits for review.')
        ->assertSuccessful();
});

it('says so when no question waits for review', function () {
    Question::factory()->verified()->create();

    artisan('quiz:reported-questions')
        ->expectsOutputToContain('No reported questions wait for review.')
        ->assertSuccessful();
});

it('prints the questions as json in the shape of the question files', function () {
    travelTo('2026-11-02 12:00:00');
    $category = Category::factory()->openTdb(13)->create();
    $question = Question::factory()->for($category)->reported()->correctAt(1)->create([
        'text_en' => 'Which planet is known as the Red Planet?',
        'text_el' => 'Ποιος πλανήτης είναι γνωστός ως ο Κόκκινος Πλανήτης;',
        'answers_en' => ['Venus', 'Mars', 'Jupiter', 'Saturn'],
        'answers_el' => ['Αφροδίτη', 'Άρης', 'Δίας', 'Κρόνος'],
    ]);

    $exitCode = Artisan::call('quiz:reported-questions', ['--json' => true]);

    expect($exitCode)->toBe(0);
    expect(json_decode(Artisan::output(), true))->toBe([[
        'id' => $question->id,
        'category' => ['slug' => 'opentdb-13', 'source' => 'opentdb'],
        'reported_at' => '2026-11-02T11:00:00+00:00',
        'verified_at' => null,
        'text' => ['en' => 'Which planet is known as the Red Planet?', 'el' => 'Ποιος πλανήτης είναι γνωστός ως ο Κόκκινος Πλανήτης;'],
        'answers' => ['en' => ['Venus', 'Mars', 'Jupiter', 'Saturn'], 'el' => ['Αφροδίτη', 'Άρης', 'Δίας', 'Κρόνος']],
        'correct' => 1,
    ]]);
});
