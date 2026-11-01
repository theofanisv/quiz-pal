<?php

use App\Models\Player;
use Illuminate\Support\Facades\Broadcast;

/*
 * Presence channel for a room. Players are anonymous, so the "player" guard
 * (session based) authorizes them instead of the default user guard.
 */
Broadcast::channel('room.{code}', function (Player $player, string $code) {
    if ($player->room?->code !== $code) {
        return false;
    }

    return ['id' => $player->id, 'name' => $player->name];
}, ['guards' => ['player']]);
