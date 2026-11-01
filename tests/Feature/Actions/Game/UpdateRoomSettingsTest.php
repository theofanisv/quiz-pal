<?php

use App\Actions\Game\UpdateRoomSettings;
use App\Events\RoomUpdated;
use App\Exceptions\GameException;
use App\Models\Category;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

it('lets the admin change rounds, timer and categories', function () {
    $room = Room::factory()->withAdmin()->create();
    [$keep, $drop] = Category::factory()->count(2)->create();
    $room->categories()->attach([$keep->id, $drop->id]);
    Event::fake([RoomUpdated::class]);

    app(UpdateRoomSettings::class)->handle($room, $room->admin, [
        'total_rounds' => 5,
        'seconds_per_question' => 30,
        'categories' => [$keep->id],
    ]);

    $room->refresh();
    expect($room->total_rounds)->toBe(5)
        ->and($room->seconds_per_question)->toBe(30)
        ->and($room->categories->pluck('id')->all())->toBe([$keep->id]);
    Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $event) => $event->reason === 'settings-changed');
});

it('forbids players other than the admin', function () {
    $room = Room::factory()->withAdmin()->create();
    $player = Player::factory()->for($room)->create();
    $category = Category::factory()->create();

    expect(fn () => app(UpdateRoomSettings::class)->handle($room, $player, [
        'total_rounds' => 5,
        'seconds_per_question' => 30,
        'categories' => [$category->id],
    ]))->toThrow(GameException::class, 'Only the room admin can do that.');

    expect($room->fresh()->total_rounds)->toBe(10);
});

it('rejects values outside the allowed ranges', function (array $overrides) {
    $room = Room::factory()->withAdmin()->create();
    $category = Category::factory()->create();

    expect(fn () => app(UpdateRoomSettings::class)->handle($room, $room->admin, array_merge([
        'total_rounds' => 10,
        'seconds_per_question' => 20,
        'categories' => [$category->id],
    ], $overrides)))->toThrow(ValidationException::class);
})->with([
    'zero rounds' => [['total_rounds' => 0]],
    'too many rounds' => [['total_rounds' => 31]],
    'timer too short' => [['seconds_per_question' => 4]],
    'timer too long' => [['seconds_per_question' => 61]],
    'no categories' => [['categories' => []]],
    'unknown category' => [['categories' => [999]]],
]);

it('rejects changes once the game has started', function () {
    $room = Room::factory()->playing()->withAdmin()->create();
    $category = Category::factory()->create();

    expect(fn () => app(UpdateRoomSettings::class)->handle($room, $room->admin, [
        'total_rounds' => 5,
        'seconds_per_question' => 30,
        'categories' => [$category->id],
    ]))->toThrow(GameException::class);
});
