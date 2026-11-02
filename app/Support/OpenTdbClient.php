<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Read-only client for the Open Trivia Database (https://opentdb.com).
 *
 * Every string is requested base64-encoded so quotes and other special characters
 * survive the trip, and decoded here. OpenTDB serves one category per request, at most
 * 50 questions per page, and allows one request per 5 seconds per IP: pacing is the
 * caller's job.
 *
 * @phpstan-type OpenTdbCategory array{id: int, name: string}
 * @phpstan-type OpenTdbQuestion array{difficulty: string, question: string, correct_answer: string, incorrect_answers: list<string>}
 * @phpstan-type OpenTdbPage array{code: int, results: list<OpenTdbQuestion>}
 */
class OpenTdbClient
{
    public const int MAX_AMOUNT = 50;

    public const int CODE_SUCCESS = 0;

    public const int CODE_NO_RESULTS = 1;

    public const int CODE_TOKEN_EMPTY = 4;

    public const int CODE_RATE_LIMITED = 5;

    /**
     * A session token makes OpenTDB skip questions it already served to this token.
     *
     * @throws HttpClientException when OpenTDB cannot be reached or answers with an HTTP error
     */
    public function requestToken(): string
    {
        $data = $this->request()->get('/api_token.php', ['command' => 'request'])->throw()->json();
        $token = is_array($data) ? ($data['token'] ?? null) : null;

        if (! is_array($data) || ($data['response_code'] ?? null) !== self::CODE_SUCCESS || ! is_string($token) || $token === '') {
            throw new RuntimeException('Open Trivia DB did not issue a session token.');
        }

        return $token;
    }

    /**
     * @return list<OpenTdbCategory>
     *
     * @throws HttpClientException when OpenTDB cannot be reached or answers with an HTTP error
     */
    public function categories(): array
    {
        $categories = $this->request()->get('/api_category.php')->throw()->json('trivia_categories');

        if (! is_array($categories)) {
            throw new RuntimeException('Open Trivia DB did not return its category list.');
        }

        /** @var list<array{id: int|string, name: string}> $categories */
        return array_map(
            fn (array $category): array => ['id' => (int) $category['id'], 'name' => (string) $category['name']],
            $categories,
        );
    }

    /**
     * One page of multiple-choice questions from a category. The response code tells
     * the caller whether the page is complete (0) or the token has no questions left (4).
     *
     * @return OpenTdbPage
     *
     * @throws HttpClientException when OpenTDB cannot be reached or answers with an HTTP error
     */
    public function questions(int $categoryId, int $amount, string $token): array
    {
        $data = $this->request()->get('/api.php', [
            'amount' => $amount,
            'category' => $categoryId,
            'type' => 'multiple',
            'encode' => 'base64',
            'token' => $token,
        ])->throw()->json();

        if (! is_array($data) || ! is_numeric($data['response_code'] ?? null)) {
            throw new RuntimeException("Open Trivia DB returned an unreadable page for category {$categoryId}.");
        }

        /** @var list<array<string, mixed>> $results */
        $results = is_array($data['results'] ?? null) ? array_values($data['results']) : [];

        return [
            'code' => (int) $data['response_code'],
            'results' => array_map($this->decodeQuestion(...), $results),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return OpenTdbQuestion
     */
    private function decodeQuestion(array $raw): array
    {
        $incorrect = is_array($raw['incorrect_answers'] ?? null) ? array_values($raw['incorrect_answers']) : [];

        return [
            'difficulty' => $this->decode($raw['difficulty'] ?? null),
            'question' => $this->decode($raw['question'] ?? null),
            'correct_answer' => $this->decode($raw['correct_answer'] ?? null),
            'incorrect_answers' => array_map($this->decode(...), $incorrect),
        ];
    }

    private function decode(mixed $value): string
    {
        $decoded = is_string($value) ? base64_decode($value, true) : false;

        return $decoded === false ? '' : $decoded;
    }

    private function request(): PendingRequest
    {
        $baseUrl = config('services.opentdb.url');

        return Http::baseUrl(is_string($baseUrl) ? $baseUrl : 'https://opentdb.com')
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->retry(3, 1000, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && $exception->response->serverError()));
    }
}
