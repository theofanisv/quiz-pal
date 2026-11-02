<?php

use App\Actions\Questions\SyncOpenTdbQuestions;
use App\Enums\QuestionSource;
use App\Models\Category;
use App\Models\Question;
use Carbon\CarbonInterval;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

use function Pest\Laravel\assertDatabaseCount;

/**
 * One raw OpenTDB result, base64-encoded the way the API serves it.
 *
 * @param  list<string>  $incorrect
 * @return array<string, mixed>
 */
function openTdbRaw(string $question, string $correct = 'Right', array $incorrect = ['Wrong 1', 'Wrong 2', 'Wrong 3'], string $difficulty = 'easy'): array
{
    return [
        'type' => base64_encode('multiple'),
        'difficulty' => base64_encode($difficulty),
        'category' => base64_encode('Any'),
        'question' => base64_encode($question),
        'correct_answer' => base64_encode($correct),
        'incorrect_answers' => array_map('base64_encode', $incorrect),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function openTdbQuestions(int $count, string $prefix = 'Question'): array
{
    return array_map(fn (int $i): array => openTdbRaw("{$prefix} {$i}?"), range(1, $count));
}

/**
 * Fake OpenTDB with a token, the given categories and, per category id, a queue of pages.
 * A page is a list of raw results, served only when the requested amount fits it, like
 * the real API; an integer in the queue is answered once as that response code.
 *
 * @param  list<array{id: int, name: string}>  $categories
 * @param  array<int, list<list<array<string, mixed>>|int>>  $queues
 */
function fakeOpenTdb(array $categories, array $queues = []): void
{
    Sleep::fake();
    Http::preventStrayRequests();
    Http::fake([
        'opentdb.com/api_token.php*' => Http::response(['response_code' => 0, 'token' => 'tok']),
        'opentdb.com/api_category.php' => Http::response(['trivia_categories' => $categories]),
        'opentdb.com/api.php*' => function (Request $request) use (&$queues) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $category = (int) $query['category'];
            $next = $queues[$category][0] ?? null;

            if (is_int($next)) {
                array_shift($queues[$category]);

                return Http::response(['response_code' => $next, 'results' => []]);
            }

            if ($next === null || (int) $query['amount'] > count($next)) {
                return Http::response(['response_code' => 4, 'results' => []]);
            }

            array_shift($queues[$category]);

            return Http::response(['response_code' => 0, 'results' => $next]);
        },
    ]);
}

/**
 * The "amount" asked for by every question request, in order.
 *
 * @return list<int>
 */
function openTdbAmounts(): array
{
    return Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/api.php'))
        ->map(function (array $pair): int {
            parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $query);

            return (int) $query['amount'];
        })
        ->values()
        ->all();
}

it('mirrors each category with its multiple-choice questions into the local bank', function () {
    $page = openTdbQuestions(49);
    $page[] = openTdbRaw('What does "DNS" stand for?', 'Domain Name System', ['Data Name Service', 'Dynamic Node Set', 'Direct Net Socket'], 'hard');
    fakeOpenTdb(
        [['id' => 18, 'name' => 'Science: Computers'], ['id' => 13, 'name' => 'Entertainment: Musicals & Theatres']],
        [18 => [$page]],
    );

    $summary = app(SyncOpenTdbQuestions::class)->handle();

    $computers = Category::query()->where('slug', 'opentdb-18')->sole();
    $musicals = Category::query()->where('slug', 'opentdb-13')->sole();
    $question = Question::query()->where('text_en', 'What does "DNS" stand for?')->sole();
    expect($summary)->toBe(['categories' => 2, 'questions' => 50, 'skipped' => 0])
        ->and($computers->source)->toBe(QuestionSource::OpenTdb)
        ->and($computers->external_id)->toBe(18)
        ->and($computers->name_en)->toBe('Science: Computers')
        ->and($computers->name_el)->toBe('Επιστήμη: Υπολογιστές')
        ->and($computers->sort_order)->toBe(18)
        ->and($computers->questions()->count())->toBe(50)
        ->and($musicals->name_el)->toBe('Ψυχαγωγία: Μιούζικαλ & Θέατρο')
        ->and($musicals->questions()->count())->toBe(0)
        ->and($question->category_id)->toBe($computers->id)
        ->and($question->text_el)->toBeNull()
        ->and($question->answers_en)->toBe(['Domain Name System', 'Data Name Service', 'Dynamic Node Set', 'Direct Net Socket'])
        ->and($question->answers_el)->toBeNull()
        ->and($question->correct_index)->toBe(0)
        ->and($question->difficulty)->toBe(3);
});

it('keeps the english name for a category without a greek translation', function () {
    fakeOpenTdb([['id' => 99, 'name' => 'Brand New Topic']]);

    app(SyncOpenTdbQuestions::class)->handle();

    expect(Category::query()->where('slug', 'opentdb-99')->sole()->name_el)->toBe('Brand New Topic');
});

it('updates a question that already exists instead of duplicating it', function () {
    $category = Category::factory()->openTdb(18)->create();
    Question::factory()->for($category)->englishOnly()->create(['text_en' => 'Question 1?', 'answers_en' => ['old', 'a', 'b', 'c']]);
    fakeOpenTdb([['id' => 18, 'name' => 'Science: Computers']], [18 => [openTdbQuestions(50)]]);

    app(SyncOpenTdbQuestions::class)->handle();

    assertDatabaseCount('categories', 1);
    assertDatabaseCount('questions', 50);
    expect(Question::query()->where('text_en', 'Question 1?')->sole()->answers_en)->toBe(['Right', 'Wrong 1', 'Wrong 2', 'Wrong 3']);
});

it('skips questions without four distinct answers or with text that does not fit', function () {
    $page = openTdbQuestions(48);
    $page[] = openTdbRaw('Repeated answers?', 'Same', ['same', 'Other', 'Third']);
    $page[] = openTdbRaw(str_repeat('x', 501));
    fakeOpenTdb([['id' => 18, 'name' => 'Science: Computers']], [18 => [$page]]);

    $summary = app(SyncOpenTdbQuestions::class)->handle();

    expect($summary)->toBe(['categories' => 1, 'questions' => 48, 'skipped' => 2]);
    assertDatabaseCount('questions', 48);
});

it('asks for smaller pages until the category runs dry', function () {
    fakeOpenTdb([['id' => 13, 'name' => 'Musicals']], [13 => [openTdbQuestions(50), openTdbQuestions(3, 'Last')]]);

    $summary = app(SyncOpenTdbQuestions::class)->handle();

    expect($summary['questions'])->toBe(53)
        ->and(openTdbAmounts())->toBe([50, 50, 25, 12, 6, 3, 3, 1]);
});

it('waits five seconds between requests', function () {
    fakeOpenTdb([['id' => 13, 'name' => 'Musicals']]);

    app(SyncOpenTdbQuestions::class)->handle();

    Http::assertSentCount(8);
    Sleep::assertSleptTimes(7);
    Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalSeconds === 5.0, times: 7);
});

it('retries a page after a rate limit answer', function () {
    fakeOpenTdb([['id' => 18, 'name' => 'Science: Computers']], [18 => [5, openTdbQuestions(50)]]);

    $summary = app(SyncOpenTdbQuestions::class)->handle();

    expect($summary['questions'])->toBe(50)
        ->and(openTdbAmounts())->toBe([50, 50, 50, 25, 12, 6, 3, 1]);
});

it('stops with an error when opentdb keeps rate limiting', function () {
    fakeOpenTdb([['id' => 18, 'name' => 'Science: Computers']], [18 => [5, 5, 5, 5]]);

    expect(fn () => app(SyncOpenTdbQuestions::class)->handle())->toThrow(RuntimeException::class, 'Science: Computers');
});

it('stops with an error when opentdb rejects the request', function () {
    fakeOpenTdb([['id' => 18, 'name' => 'Science: Computers']], [18 => [2]]);

    expect(fn () => app(SyncOpenTdbQuestions::class)->handle())->toThrow(RuntimeException::class);
    assertDatabaseCount('questions', 0);
});

it('syncs only the requested categories', function () {
    fakeOpenTdb([['id' => 18, 'name' => 'Science: Computers'], ['id' => 13, 'name' => 'Musicals']], [13 => [openTdbQuestions(50)]]);

    $summary = app(SyncOpenTdbQuestions::class)->handle([13]);

    expect($summary)->toBe(['categories' => 1, 'questions' => 50, 'skipped' => 0])
        ->and(Category::query()->pluck('slug')->all())->toBe(['opentdb-13']);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'category=18'));
});
