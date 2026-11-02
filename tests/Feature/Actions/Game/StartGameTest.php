<?php

use App\Actions\Game\StartGame;
use App\Enums\RoomStatus;
use App\Events\RoomUpdated;
use App\Exceptions\GameException;
use App\Models\Category;
use App\Models\Player;
use App\Models\Question;
use App\Models\Room;
use App\Support\LiveUpdates;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\freezeSecond;
use function Pest\Laravel\mock;

it('creates every round from the chosen categories and opens the first one', function () {
    freezeSecond();
    $room = Room::factory()->withAdmin()->create(['total_rounds' => 4, 'seconds_per_question' => 15]);
    [$history, $sports, $ignored] = Category::factory()->count(3)->create();
    Question::factory()->for($history)->count(3)->create();
    Question::factory()->for($sports)->count(3)->create();
    Question::factory()->for($ignored)->count(3)->create();
    $room->categories()->attach([$history->id, $sports->id]);
    $room->admin->update(['is_ready' => true, 'score' => 42]);
    Event::fake([RoomUpdated::class]);

    app(StartGame::class)->handle($room, $room->admin);

    $room->refresh();
    $rounds = $room->rounds;
    expect($room->status)->toBe(RoomStatus::Playing)
        ->and($room->current_round)->toBe(1)
        ->and($room->admin->score)->toBe(0)
        ->and($rounds)->toHaveCount(4)
        ->and($rounds->pluck('round_number')->all())->toBe([1, 2, 3, 4])
        ->and($rounds->pluck('question_id')->unique())->toHaveCount(4);
    expect($rounds->map(fn ($round) => $round->question->category_id)->countBy()->sortKeys()->all())
        ->toBe(collect([$history->id => 2, $sports->id => 2])->sortKeys()->all());
    expect($rounds->first()->started_at->equalTo(now()->addSeconds(3)))->toBeTrue()
        ->and($rounds->first()->ends_at->equalTo(now()->addSeconds(3 + 15)))->toBeTrue()
        ->and($rounds->get(1)->started_at)->toBeNull();
    foreach ($rounds as $round) {
        expect(collect($round->answer_order)->sort()->values()->all())->toBe([0, 1, 2, 3]);
    }
    Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $event) => $event->reason === 'started');
});

it('ignores disconnected players when checking that everyone is ready', function () {
    $room = Room::factory()->withAdmin()->create(['total_rounds' => 1]);
    $room->admin->update(['is_ready' => true]);
    Player::factory()->for($room)->disconnected()->create();
    $category = Category::factory()->has(Question::factory())->create();
    $room->categories()->attach($category);

    app(StartGame::class)->handle($room, $room->admin);

    expect($room->fresh()->status)->toBe(RoomStatus::Playing);
});

it('refuses to start while a connected player is not ready', function () {
    $room = Room::factory()->withAdmin()->create();
    $room->admin->update(['is_ready' => true]);
    Player::factory()->for($room)->create();

    expect(fn () => app(StartGame::class)->handle($room, $room->admin))
        ->toThrow(GameException::class, 'Everyone has to be ready before the game can start.');

    expect($room->fresh()->status)->toBe(RoomStatus::Lobby);
});

it('refuses to start when the chosen categories hold fewer questions than rounds', function () {
    $room = Room::factory()->withAdmin()->create(['total_rounds' => 5]);
    $room->admin->update(['is_ready' => true]);
    $category = Category::factory()->has(Question::factory()->count(3))->create();
    $room->categories()->attach($category);

    expect(fn () => app(StartGame::class)->handle($room, $room->admin))
        ->toThrow(GameException::class, 'Only 3 questions are available for the chosen categories. Choose more categories or fewer rounds.');

    expect($room->rounds()->count())->toBe(0);
});

it('forbids players other than the admin', function () {
    $room = Room::factory()->withAdmin()->create();
    $player = Player::factory()->for($room)->ready()->create();
    $room->admin->update(['is_ready' => true]);

    expect(fn () => app(StartGame::class)->handle($room, $player))
        ->toThrow(GameException::class, 'Only the room admin can do that.');
});

it('refuses to start while live updates are unavailable', function () {
    $room = Room::factory()->withAdmin()->create(['total_rounds' => 1]);
    $room->admin->update(['is_ready' => true]);
    $room->categories()->attach(Category::factory()->has(Question::factory())->create());
    mock(LiveUpdates::class)->shouldReceive('isAvailable')->once()->andReturnFalse();

    expect(fn () => app(StartGame::class)->handle($room, $room->admin))
        ->toThrow(GameException::class, 'Live updates are unavailable, so the game cannot start right now.');

    expect($room->fresh()->status)->toBe(RoomStatus::Lobby)
        ->and($room->rounds()->count())->toBe(0);
});

it('leaves out questions that wait for review', function () {
    $room = Room::factory()->withAdmin()->create(['total_rounds' => 2]);
    $room->admin->update(['is_ready' => true]);
    $category = Category::factory()->create();
    $playable = Question::factory()->for($category)->create();
    $verified = Question::factory()->for($category)->verified()->create();
    Question::factory()->for($category)->reported()->count(3)->create();
    $room->categories()->attach($category);

    app(StartGame::class)->handle($room, $room->admin);

    expect($room->rounds()->pluck('question_id')->sort()->values()->all())->toBe([$playable->id, $verified->id]);
});

it('does not count questions that wait for review as available', function () {
    $room = Room::factory()->withAdmin()->create(['total_rounds' => 2]);
    $room->admin->update(['is_ready' => true]);
    $category = Category::factory()->create();
    Question::factory()->for($category)->create();
    Question::factory()->for($category)->reported()->count(2)->create();
    $room->categories()->attach($category);

    expect(fn () => app(StartGame::class)->handle($room, $room->admin))
        ->toThrow(GameException::class, 'Only 1 questions are available for the chosen categories. Choose more categories or fewer rounds.');

    expect($room->fresh()->status)->toBe(RoomStatus::Lobby);
});

it('takes the questions shown least often', function () {
    $room = Room::factory()->withAdmin()->create(['total_rounds' => 2]);
    $room->admin->update(['is_ready' => true]);
    $category = Category::factory()->create();
    $unseen = Question::factory()->for($category)->count(2)->create();
    Question::factory()->for($category)->shown(3)->count(2)->create();
    $room->categories()->attach($category);

    app(StartGame::class)->handle($room, $room->admin);

    expect($room->rounds()->pluck('question_id')->sort()->values()->all())->toBe($unseen->pluck('id')->sort()->values()->all());
});

it('takes the question shown longest ago when questions were shown equally often', function () {
    $room = Room::factory()->withAdmin()->create(['total_rounds' => 1]);
    $room->admin->update(['is_ready' => true]);
    $category = Category::factory()->create();
    $shownLongAgo = Question::factory()->for($category)->shown(1, now()->subDay())->create();
    Question::factory()->for($category)->shown(1, now()->subHour())->create();
    $room->categories()->attach($category);

    app(StartGame::class)->handle($room, $room->admin);

    expect($room->rounds()->pluck('question_id')->all())->toBe([$shownLongAgo->id]);
});

it('counts only the first question as shown when the game starts', function () {
    freezeSecond();
    $room = Room::factory()->withAdmin()->create(['total_rounds' => 2]);
    $room->admin->update(['is_ready' => true]);
    $room->categories()->attach(Category::factory()->has(Question::factory()->count(2))->create());

    app(StartGame::class)->handle($room, $room->admin);

    [$first, $second] = $room->rounds()->orderBy('round_number')->with('question')->get()->map->question;
    expect($first->times_shown)->toBe(1)
        ->and($first->last_shown_at->equalTo(now()->addSeconds(3)))->toBeTrue();
    expect($second->times_shown)->toBe(0)
        ->and($second->last_shown_at)->toBeNull();
});
