<?php

use App\Support\LiveUpdates;
use Illuminate\Support\Facades\Http;

function useReverbAt(string $host, int $port, string $scheme = 'http'): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.options' => ['host' => $host, 'port' => $port, 'scheme' => $scheme],
    ]);
}

it('counts a broadcaster without a server as available', function () {
    config(['broadcasting.default' => 'log']);
    Http::preventStrayRequests();

    expect(app(LiveUpdates::class)->isAvailable())->toBeTrue();
});

it('builds the health url from the reverb publishing options', function () {
    useReverbAt('127.0.0.1', 8001);

    expect(app(LiveUpdates::class)->healthUrl())->toBe('http://127.0.0.1:8001/up');
});

it('is available when reverb answers its health check', function () {
    useReverbAt('127.0.0.1', 8001);
    Http::preventStrayRequests();
    Http::fake(['127.0.0.1:8001/up' => Http::response(['health' => 'OK'])]);

    expect(app(LiveUpdates::class)->isAvailable())->toBeTrue();
});

it('is unavailable when reverb cannot be reached', function () {
    useReverbAt('127.0.0.1', 8001);
    Http::preventStrayRequests();
    Http::fake(['127.0.0.1:8001/up' => Http::failedConnection()]);

    expect(app(LiveUpdates::class)->isAvailable())->toBeFalse();
});

it('is unavailable when reverb answers with an error', function () {
    useReverbAt('127.0.0.1', 8001);
    Http::preventStrayRequests();
    Http::fake(['127.0.0.1:8001/up' => Http::response('', 503)]);

    expect(app(LiveUpdates::class)->isAvailable())->toBeFalse();
});
