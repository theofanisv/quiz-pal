<?php

use App\Actions\Game\CreateRoom;
use App\Enums\RoomStatus;
use App\Models\Category;
use App\Support\RoomCode;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\assertDatabaseCount;

it('creates a lobby room whose creator is the admin with every category selected', function () {
    Category::factory()->count(3)->create();

    $room = app(CreateRoom::class)->handle('Maria');

    expect(RoomCode::isValid($room->code))->toBeTrue()
        ->and($room->status)->toBe(RoomStatus::Lobby)
        ->and($room->players)->toHaveCount(1)
        ->and($room->admin->name)->toBe('Maria')
        ->and($room->categories)->toHaveCount(3);
});

it('does not create a room when the creator name is invalid', function () {
    expect(fn () => app(CreateRoom::class)->handle('x'))
        ->toThrow(ValidationException::class);

    assertDatabaseCount('rooms', 0);
});
