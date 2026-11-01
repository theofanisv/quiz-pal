<?php

use App\Models\Player;
use App\Models\Room;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => '1',
    ]);

    // Channels are registered on the broadcaster that was active at boot (the null
    // driver in tests), so register them again on the Reverb driver under test.
    require base_path('routes/channels.php');
});

it('authorizes a player of the room and shares their name with the channel', function () {
    $player = Player::factory()->create(['name' => 'Nikos']);

    actingAs($player, 'player')
        ->post('/broadcasting/auth', [
            'channel_name' => 'presence-room.'.$player->room->code,
            'socket_id' => '1234.5678',
        ])
        ->assertOk()
        ->assertJsonPath('channel_data', json_encode(['user_id' => (string) $player->id, 'user_info' => ['id' => $player->id, 'name' => 'Nikos']]));
});

it('forbids a player of another room', function () {
    $room = Room::factory()->create();
    $stranger = Player::factory()->create();

    actingAs($stranger, 'player')
        ->post('/broadcasting/auth', [
            'channel_name' => 'presence-room.'.$room->code,
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});

it('forbids a visitor who has not joined', function () {
    $room = Room::factory()->create();

    post('/broadcasting/auth', [
        'channel_name' => 'presence-room.'.$room->code,
        'socket_id' => '1234.5678',
    ])->assertForbidden();
});
