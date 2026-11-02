<?php

use App\Enums\RoomStatus;
use App\Livewire\Game\Room as RoomPage;
use App\Models\Category;
use App\Models\Player;
use App\Models\Question;
use App\Models\Room;
use App\Models\Round;
use App\Support\LiveUpdates;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\freezeSecond;
use function Pest\Laravel\get;
use function Pest\Laravel\mock;
use function Pest\Laravel\travel;

describe('joining', function () {
    it('shows the join form to a visitor, even with a lowercase code in the url', function () {
        $room = Room::factory()->withAdmin()->create(['code' => 'KXWP']);

        get('/room/kxwp')
            ->assertOk()
            ->assertSeeLivewire(RoomPage::class)
            ->assertSee('Your name')
            ->assertSee('1 player is already here.');
    });

    it('returns 404 for an unknown room', function () {
        get('/room/ZZZZ')->assertNotFound();
    });

    it('registers the visitor as a player, signs them in and reloads the room', function () {
        $room = Room::factory()->withAdmin()->create();

        Livewire::test(RoomPage::class, ['room' => $room])
            ->set('name', 'Nikos')
            ->call('join')
            ->assertRedirect(route('rooms.show', $room));

        $player = $room->players()->where('name', 'Nikos')->sole();
        expect(Auth::guard('player')->id())->toBe($player->id);
    });

    it('shows the error when the name is already taken', function () {
        $room = Room::factory()->withAdmin('Maria')->create();

        Livewire::test(RoomPage::class, ['room' => $room])
            ->set('name', 'maria')
            ->call('join')
            ->assertHasErrors('name')
            ->assertSee('That name is already taken in this room.')
            ->assertNoRedirect();
    });

    it('tells a visitor that a game is in progress instead of showing the form', function () {
        $room = Room::factory()->playing()->withAdmin()->create();

        get(route('rooms.show', $room))
            ->assertOk()
            ->assertSee('A game is in progress')
            ->assertDontSee('Your name');
    });

    it('shows the join form to a player signed into another room', function () {
        $room = Room::factory()->withAdmin()->create();
        $elsewhere = Player::factory()->create();

        actingAs($elsewhere, 'player')
            ->get(route('rooms.show', $room))
            ->assertOk()
            ->assertSee('Your name');
    });
});

describe('lobby', function () {
    it('shows the players, the share link and the ready button', function () {
        $room = Room::factory()->withAdmin('Maria')->create();
        $player = Player::factory()->for($room)->ready()->create(['name' => 'Nikos']);

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSee('Maria')
            ->assertSee('Nikos')
            ->assertSee(route('rooms.show', $room))
            ->assertSee('Ready! Tap to undo')
            ->assertSee('Waiting for Maria to start the game.')
            ->assertDontSee('Start game');
    });

    it('toggles readiness', function () {
        $player = Player::factory()->for(Room::factory()->withAdmin())->create();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $player->room])
            ->call('toggleReady')
            ->assertSee('Ready! Tap to undo');

        expect($player->fresh()->is_ready)->toBeTrue();
    });

    it('saves settings as the admin changes them', function () {
        $room = Room::factory()->withAdmin()->create();
        [$keep, $drop] = Category::factory()->count(2)->create();
        $room->categories()->attach([$keep->id, $drop->id]);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSee('Start game')
            ->set('totalRounds', 5)
            ->set('categories', [(string) $keep->id])
            ->assertHasNoErrors();

        $room->refresh();
        expect($room->total_rounds)->toBe(5)
            ->and($room->categories->pluck('id')->all())->toBe([$keep->id]);
    });

    it('shows a validation error for an invalid setting without saving it', function () {
        $room = Room::factory()->withAdmin()->create();
        $room->categories()->attach(Category::factory()->create());

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->set('totalRounds', 99)
            ->assertHasErrors('totalRounds');

        expect($room->fresh()->total_rounds)->toBe(10);
    });

    it('shows a validation error when the admin clears a number setting', function (string $property, string $message, string $column, int $kept) {
        $room = Room::factory()->withAdmin()->create();
        $room->categories()->attach(Category::factory()->create());

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->set($property, null)
            ->assertHasErrors($property)
            ->assertSee($message);

        expect($room->fresh()->{$column})->toBe($kept);
    })->with([
        'rounds' => ['totalRounds', 'The rounds field is required.', 'total_rounds', 10],
        'seconds per question' => ['secondsPerQuestion', 'The seconds per question field is required.', 'seconds_per_question', 20],
    ]);

    it('lets the admin remove a player', function () {
        $room = Room::factory()->withAdmin()->create();
        $player = Player::factory()->for($room)->create(['name' => 'Nikos']);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('removePlayer', $player->id)
            ->assertDontSee('Nikos');

        expect($player->fresh())->toBeNull();
    });

    it('starts the game and shows the first question', function () {
        $room = Room::factory()->withAdmin()->create(['total_rounds' => 1]);
        $room->admin->update(['is_ready' => true]);
        $category = Category::factory()->has(Question::factory()->state(['text_en' => 'What is the capital of Greece?']))->create();
        $room->categories()->attach($category);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('startGame')
            ->assertSee('Round 1 of 1')
            ->assertSee('What is the capital of Greece?');

        expect($room->fresh()->status)->toBe(RoomStatus::Playing);
    });

    it('keeps the room in the lobby when someone other than the admin tries to start', function () {
        $room = Room::factory()->withAdmin()->create();
        $room->admin->update(['is_ready' => true]);
        $player = Player::factory()->for($room)->ready()->create();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('startGame')
            ->assertOk();

        expect($room->fresh()->status)->toBe(RoomStatus::Lobby);
    });

    it('warns the admin and blocks the start while live updates are unavailable', function () {
        $room = Room::factory()->withAdmin()->create();
        $room->admin->update(['is_ready' => true]);
        mock(LiveUpdates::class)->shouldReceive('isAvailable')->andReturnFalse();

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSee('Live updates are unavailable')
            ->assertSee('Start game');

        expect($room->fresh()->status)->toBe(RoomStatus::Lobby);
    });

    it('forbids actions from a visitor who is not a player', function () {
        $room = Room::factory()->withAdmin()->create();

        Livewire::test(RoomPage::class, ['room' => $room])
            ->call('toggleReady')
            ->assertForbidden();
    });
});

describe('playing', function () {
    /**
     * @return array{0: Room, 1: Round, 2: Player}
     */
    function roomInPlay(): array
    {
        $room = Room::factory()->playing()->withAdmin('Maria')->create(['total_rounds' => 2, 'seconds_per_question' => 20]);
        $question = Question::factory()->correctAt(1)->create([
            'text_en' => 'Which planet is known as the Red Planet?',
            'answers_en' => ['Venus', 'Mars', 'Jupiter', 'Saturn'],
        ]);
        $round = Round::factory()->for($room)->for($question)->started(20)->create(['round_number' => 1]);
        $player = Player::factory()->for($room)->create(['name' => 'Nikos']);

        return [$room, $round, $player];
    }

    it('shows a get-ready countdown while the question is scheduled', function () {
        $room = Room::factory()->playing()->withAdmin()->create();
        Round::factory()->for($room)->pending()->create(['round_number' => 1]);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSee('Get ready…')
            ->assertSee('Round 1 of 10');
    });

    it('shows the question, the shuffled answers and who has answered', function () {
        [$room, $round, $player] = roomInPlay();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSee('Which planet is known as the Red Planet?')
            ->assertSeeInOrder(['Venus', 'Mars', 'Jupiter', 'Saturn'])
            ->assertSee('Time left')
            ->assertSee('Thinking…')
            ->assertDontSee('Next question');
    });

    it('records the answer and marks the player as answered without revealing', function () {
        [$room, $round, $player] = roomInPlay();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('answer', 1)
            ->assertSee('Answered')
            ->assertDontSee('Scores');

        expect($round->answers()->where('player_id', $player->id)->sole()->is_correct)->toBeTrue();
    });

    it('reveals the answers and the scores once everyone has answered', function () {
        freezeSecond();
        [$room, $round, $player] = roomInPlay();
        Livewire::actingAs($player, 'player')->test(RoomPage::class, ['room' => $room])->call('answer', 1);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('answer', 0)
            ->assertSee('Scores')
            ->assertSee('+1000')
            ->assertSee('Next question');

        expect($player->fresh()->score)->toBe(1000)
            ->and($room->admin->fresh()->score)->toBe(0);
    });

    it('ignores time up before the timer has run out', function () {
        freezeSecond();
        [$room, $round, $player] = roomInPlay();
        travel(10)->seconds();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('timeUp')
            ->assertSee('Time left');

        expect($round->fresh()->isRevealed())->toBeFalse();
    });

    it('reveals the round when a client reports that time is up', function () {
        freezeSecond();
        [$room, $round, $player] = roomInPlay();
        travel(21)->seconds();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('timeUp')
            ->assertSee('Scores')
            ->assertSee('Waiting for Maria to continue.');

        expect($round->fresh()->isRevealed())->toBeTrue();
    });

    it('lets the admin move to the next question', function () {
        [$room, $round, $player] = roomInPlay();
        $round->update(['revealed_at' => now()]);
        Round::factory()->for($room)->for(Question::factory()->state(['text_en' => 'Second question?']))->create(['round_number' => 2]);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('nextRound')
            ->assertSee('Round 2 of 2')
            ->assertSee('Second question?');
    });

    it('shows the final results after the last round', function () {
        $room = Room::factory()->playing(1)->withAdmin('Maria')->create(['total_rounds' => 1]);
        $room->admin->update(['score' => 800]);
        Player::factory()->for($room)->create(['name' => 'Nikos', 'score' => 1200]);
        Round::factory()->for($room)->revealed()->create(['round_number' => 1]);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('nextRound')
            ->assertSee('Final results')
            ->assertSee('Nikos wins!')
            ->assertSeeInOrder(['Nikos', '1200', 'Maria', '800'])
            ->assertSee('Play again');
    });

    it('marks players who are not on the presence channel as away', function () {
        [$room, $round, $player] = roomInPlay();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('syncPresence', [['id' => $player->id, 'name' => $player->name]])
            ->assertSee('Away');

        expect($room->admin->fresh()->is_connected)->toBeFalse()
            ->and($player->fresh()->is_connected)->toBeTrue();
    });
});

describe('finished', function () {
    it('lets the admin start a new game in the same room', function () {
        $room = Room::factory()->finished()->withAdmin()->create();
        Player::factory()->for($room)->ready()->create(['score' => 500]);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('playAgain')
            ->assertSee('Start game');

        expect($room->fresh()->status)->toBe(RoomStatus::Lobby)
            ->and($room->players()->sum('score'))->toBe(0);
    });
});
