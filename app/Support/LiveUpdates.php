<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Whether the configured broadcaster can deliver live updates right now.
 *
 * Reverb is mandatory for a game: the lobby refuses to start one while its server is down.
 * Reverb exposes GET /up on the same host and port the application publishes events to.
 * Any other driver (log, null) has no server to check and counts as available.
 */
class LiveUpdates
{
    public function isAvailable(): bool
    {
        if (config('broadcasting.default') !== 'reverb') {
            return true;
        }

        try {
            return Http::connectTimeout(1)->timeout(2)->get($this->healthUrl())->ok();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function healthUrl(): string
    {
        $options = config('broadcasting.connections.reverb.options');
        $options = is_array($options) ? $options : [];

        $scheme = is_string($options['scheme'] ?? null) ? $options['scheme'] : 'https';
        $host = is_string($options['host'] ?? null) && $options['host'] !== '' ? $options['host'] : '127.0.0.1';
        $port = is_numeric($options['port'] ?? null) ? (int) $options['port'] : ($scheme === 'https' ? 443 : 80);

        return "{$scheme}://{$host}:{$port}/up";
    }
}
