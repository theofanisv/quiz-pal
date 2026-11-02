<?php

use App\Models\Category;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

use function Pest\Laravel\artisan;

/**
 * Fake an OpenTDB with the given categories and no questions left for any of them.
 *
 * @param  list<array{id: int, name: string}>  $categories
 */
function fakeEmptyOpenTdb(array $categories): void
{
    Sleep::fake();
    Http::preventStrayRequests();
    Http::fake([
        'opentdb.com/api_token.php*' => Http::response(['response_code' => 0, 'token' => 'tok']),
        'opentdb.com/api_category.php' => Http::response(['trivia_categories' => $categories]),
        'opentdb.com/api.php*' => Http::response(['response_code' => 4, 'results' => []]),
    ]);
}

it('syncs open trivia db and reports what it imported', function () {
    fakeEmptyOpenTdb([['id' => 13, 'name' => 'Musicals'], ['id' => 20, 'name' => 'Mythology']]);

    artisan('quiz:sync-opentdb')
        ->expectsOutputToContain('Synced 0 questions in 2 categories from Open Trivia DB (0 skipped).')
        ->assertSuccessful();

    expect(Category::query()->pluck('slug')->all())->toBe(['opentdb-13', 'opentdb-20']);
});

it('limits the run to the requested category ids', function () {
    fakeEmptyOpenTdb([['id' => 13, 'name' => 'Musicals'], ['id' => 20, 'name' => 'Mythology']]);

    artisan('quiz:sync-opentdb', ['--category' => [20]])
        ->expectsOutputToContain('in 1 category')
        ->assertSuccessful();

    expect(Category::query()->pluck('slug')->all())->toBe(['opentdb-20']);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'category=13'));
});

it('fails with the reason and reports it when open trivia db cannot be reached', function () {
    Sleep::fake();
    Exceptions::fake();
    Http::preventStrayRequests();
    Http::fake(['opentdb.com/api_token.php*' => Http::failedConnection()]);

    artisan('quiz:sync-opentdb')
        ->expectsOutputToContain('Open Trivia DB sync stopped:')
        ->assertFailed();

    Exceptions::assertReported(ConnectionException::class);
});
