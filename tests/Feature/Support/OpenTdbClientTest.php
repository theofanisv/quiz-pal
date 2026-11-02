<?php

use App\Support\OpenTdbClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * @return array<string, string>
 */
function openTdbQuery(Request $request): array
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return array_map('strval', $query);
}

it('requests a session token', function () {
    Http::preventStrayRequests();
    Http::fake(['opentdb.com/api_token.php*' => Http::response([
        'response_code' => 0,
        'response_message' => 'Token Generated Successfully!',
        'token' => 'abc123',
    ])]);

    expect(app(OpenTdbClient::class)->requestToken())->toBe('abc123');

    Http::assertSent(fn (Request $request): bool => openTdbQuery($request) === ['command' => 'request']);
});

it('fails loudly when no token is issued', function () {
    Http::preventStrayRequests();
    Http::fake(['opentdb.com/api_token.php*' => Http::response(['response_code' => 2])]);

    expect(fn () => app(OpenTdbClient::class)->requestToken())->toThrow(RuntimeException::class);
});

it('lists the categories with integer ids', function () {
    Http::preventStrayRequests();
    Http::fake(['opentdb.com/api_category.php' => Http::response(['trivia_categories' => [
        ['id' => 9, 'name' => 'General Knowledge'],
        ['id' => 18, 'name' => 'Science: Computers'],
    ]])]);

    expect(app(OpenTdbClient::class)->categories())->toBe([
        ['id' => 9, 'name' => 'General Knowledge'],
        ['id' => 18, 'name' => 'Science: Computers'],
    ]);
});

it('fetches a page of multiple-choice questions and decodes the base64 fields', function () {
    Http::preventStrayRequests();
    Http::fake(['opentdb.com/api.php*' => Http::response([
        'response_code' => 0,
        'results' => [[
            'type' => base64_encode('multiple'),
            'difficulty' => base64_encode('hard'),
            'category' => base64_encode('Science: Computers'),
            'question' => base64_encode('What does "DNS" stand for?'),
            'correct_answer' => base64_encode('Domain Name System'),
            'incorrect_answers' => [base64_encode('Data Name Service'), base64_encode('Dynamic Node Set'), base64_encode('Direct Net Socket')],
        ]],
    ])]);

    $page = app(OpenTdbClient::class)->questions(categoryId: 18, amount: 50, token: 'abc123');

    expect($page)->toBe([
        'code' => 0,
        'results' => [[
            'difficulty' => 'hard',
            'question' => 'What does "DNS" stand for?',
            'correct_answer' => 'Domain Name System',
            'incorrect_answers' => ['Data Name Service', 'Dynamic Node Set', 'Direct Net Socket'],
        ]],
    ]);
    Http::assertSent(fn (Request $request): bool => openTdbQuery($request) === [
        'amount' => '50',
        'category' => '18',
        'type' => 'multiple',
        'encode' => 'base64',
        'token' => 'abc123',
    ]);
});

it('returns the response code with an empty page when opentdb has no questions left', function () {
    Http::preventStrayRequests();
    Http::fake(['opentdb.com/api.php*' => Http::response(['response_code' => 4, 'results' => []])]);

    expect(app(OpenTdbClient::class)->questions(13, 50, 'abc123'))->toBe(['code' => 4, 'results' => []]);
});

it('retries a dropped connection before giving up', function () {
    Sleep::fake();
    Http::preventStrayRequests();
    Http::fake(['opentdb.com/api_category.php' => Http::sequence()
        ->pushFailedConnection()
        ->push(['trivia_categories' => [['id' => 9, 'name' => 'General Knowledge']]]),
    ]);

    expect(app(OpenTdbClient::class)->categories())->toBe([['id' => 9, 'name' => 'General Knowledge']]);

    Http::assertSentCount(2);
});

it('throws once opentdb keeps answering with a server error', function () {
    Sleep::fake();
    Http::preventStrayRequests();
    Http::fake(['opentdb.com/api_category.php' => Http::response('', 503)]);

    expect(fn () => app(OpenTdbClient::class)->categories())->toThrow(RequestException::class);

    Http::assertSentCount(3);
});
