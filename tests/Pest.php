<?php

use App\Models\Answer;
use App\Models\Game;
use App\Models\Question;
use App\Models\Round;
use App\Support\RemovedOpenTdbQuestions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Point the list of removed Open Trivia DB questions at a temporary file holding these entries.
 *
 * @param  list<array<string, mixed>>  $questions
 */
function useRemovedOpenTdbQuestions(array $questions): void
{
    $path = (string) tempnam(sys_get_temp_dir(), 'opentdb-removed-');
    file_put_contents($path, json_encode(['questions' => $questions], JSON_THROW_ON_ERROR));

    app()->instance(RemovedOpenTdbQuestions::class, new RemovedOpenTdbQuestions($path));
}

/**
 * Record a game in the history: each entry is one round, in order. The answer shows 10 seconds after the question
 * and stays on screen for `pause` seconds until the admin moves on. Answers are [choice, points] pairs to questions
 * whose correct answer is the first; `present` defaults to the number of answers.
 *
 * @param  array<int, array{question?: Question, pause?: int, present?: int|null, answers?: array<int, array{0: int, 1: int}>, revealed?: bool}>  $rounds
 */
function playRecordedGame(array $rounds, bool $finished = true): Game
{
    $game = Game::factory()->create(['total_rounds' => count($rounds)]);
    $startedAt = CarbonImmutable::parse('2026-11-01 20:00:00');
    $advancedAt = $startedAt;

    foreach (array_values($rounds) as $index => $spec) {
        $question = $spec['question'] ?? Question::factory()->correctAt(0)->create();
        $answers = $spec['answers'] ?? [[0, 750], [0, 750]];
        $revealedAt = $startedAt->addSeconds(10);

        $round = Round::factory()->for($game)->for($question)->create([
            'round_number' => $index + 1,
            'started_at' => $startedAt,
            'ends_at' => $startedAt->addSeconds(20),
            'revealed_at' => ($spec['revealed'] ?? true) ? $revealedAt : null,
            'players_present' => array_key_exists('present', $spec) ? $spec['present'] : count($answers),
        ]);

        foreach ($answers as [$choice, $points]) {
            Answer::factory()->for($round)->create([
                'choice' => $choice,
                'is_correct' => $question->isCorrect($choice),
                'points' => $points,
            ]);
        }

        $advancedAt = $revealedAt->addSeconds($spec['pause'] ?? 10);
        $startedAt = $advancedAt->addSeconds(Round::START_DELAY_SECONDS);
    }

    if ($finished) {
        $game->update(['finished_at' => $advancedAt]);
    }

    return $game;
}
