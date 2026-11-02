<?php

use App\Models\Category;
use App\Models\Question;

use function Pest\Laravel\artisan;

/**
 * One game of three science questions. The scores follow from the documented formula: A has double the usual
 * pause, everyone answered, and half got it (raw 1.0); B was answered instantly by everyone (raw 0.5); C as well,
 * but half the players let the time run out (raw 0.361). Each has one play, so it sits 1/4 of the way from the
 * average raw (0.620) to its own.
 *
 * @return array{a: Question, b: Question, c: Question}
 */
function playScienceGame(): array
{
    $science = Category::factory()->create(['slug' => 'science']);
    $questions = [
        'a' => Question::factory()->for($science)->correctAt(0)->create(['text_en' => 'Which planet is red?']),
        'b' => Question::factory()->for($science)->correctAt(0)->reported()->create(['text_en' => 'How many legs has a spider?']),
        'c' => Question::factory()->for($science)->correctAt(0)->create(['text_en' => 'What is H2O?']),
    ];

    playRecordedGame([
        ['question' => $questions['a'], 'pause' => 20, 'answers' => [[0, 1000], [1, 0]]],
        ['question' => $questions['b'], 'answers' => [[0, 1000], [0, 1000]]],
        ['question' => $questions['c'], 'present' => 4, 'answers' => [[0, 1000], [0, 1000]]],
    ]);

    return $questions;
}

it('lists per category and per question how much players seem to like them', function () {
    ['a' => $a, 'b' => $b, 'c' => $c] = playScienceGame();

    artisan('quiz:question-likeness', ['--min-plays' => 1, '--limit' => 2])
        ->expectsTable(
            ['Category', 'Source', 'Questions', 'Plays', 'Score', 'Talk', 'Answered', 'Correct'],
            [['science', 'custom', 3, 3, '62.0', '1.3x', '83%', '83%']],
        )
        ->expectsOutputToContain('Most liked')
        ->expectsTable(
            ['ID', 'Category', 'Question', 'Plays', 'Score', 'Talk', 'Answered', 'Correct', 'Gotcha'],
            [
                [$a->id, 'science', 'Which planet is red?', 1, '71.5', '2.0x', '100%', '50%', '-'],
                [$b->id, 'science', 'How many legs has a spider? [reported]', 1, '59.0', '1.0x', '100%', '100%', '-'],
            ],
        )
        ->expectsOutputToContain('Least liked')
        ->expectsTable(
            ['ID', 'Category', 'Question', 'Plays', 'Score', 'Talk', 'Answered', 'Correct', 'Gotcha'],
            [[$c->id, 'science', 'What is H2O?', 1, '55.6', '1.0x', '50%', '100%', '-']],
        )
        ->assertSuccessful();
});

it('leaves out the question lists until a question was played often enough', function () {
    playScienceGame();

    artisan('quiz:question-likeness')
        ->expectsOutputToContain('No question was played at least 2 times yet.')
        ->doesntExpectOutputToContain('Most liked')
        ->assertSuccessful();
});

it('only lists the questions of the given category', function () {
    playScienceGame();
    $history = Category::factory()->create(['slug' => 'history']);
    playRecordedGame([['question' => Question::factory()->for($history)->correctAt(0)->create(['text_en' => 'Who built Rome?'])]]);

    artisan('quiz:question-likeness', ['--min-plays' => 1, '--category' => 'history'])
        ->expectsOutputToContain('Who built Rome?')
        ->doesntExpectOutputToContain('science')
        ->assertSuccessful();
});

it('says so when no game has revealed a question yet', function () {
    artisan('quiz:question-likeness')
        ->expectsOutputToContain('No game has revealed a question yet.')
        ->assertSuccessful();
});
