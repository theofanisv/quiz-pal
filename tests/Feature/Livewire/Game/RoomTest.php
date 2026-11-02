<?php

use App\Enums\QuestionSource;
use App\Enums\RoomStatus;
use App\Livewire\Game\Room as RoomPage;
use App\Models\Answer;
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

    it('keeps the room page out of search results but describes the invite for link previews', function () {
        $room = Room::factory()->withAdmin()->create(['code' => 'KXWP']);

        get(route('rooms.show', $room))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('<meta property="og:url" content="'.route('rooms.show', $room).'">', false)
            ->assertSee('<meta name="description" content="Your friend invited you to a quiz night. Open the link, pick a name and play.">', false)
            ->assertDontSee('rel="canonical"', false)
            ->assertSee('href="'.route('locale.switch', 'el').'"', false);
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
            ->assertSeeHtml('data-player-color="'.$player->color->value.'"')
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

    it('lets the admin switch to open trivia db and swaps the category list', function () {
        $room = Room::factory()->withAdmin()->create();
        $room->categories()->attach(Category::factory()->create(['name_en' => 'Greek History']));
        $trivia = Category::factory()->openTdb(18)->has(Question::factory()->englishOnly())->create(['name_en' => 'Science: Computers']);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSee('Question source')
            ->assertSee('Greek History')
            ->assertDontSee('Science: Computers')
            ->assertDontSee('Not available on this server yet')
            ->set('questionSource', 'opentdb')
            ->assertHasNoErrors()
            ->assertSee('Science: Computers')
            ->assertDontSee('Greek History');

        $room->refresh();
        expect($room->question_source)->toBe(QuestionSource::OpenTdb)
            ->and($room->categories->pluck('id')->all())->toBe([$trivia->id]);
    });

    it('tells the admin to import the open trivia db snapshot before it can be chosen', function () {
        $room = Room::factory()->withAdmin()->create();
        $room->categories()->attach(Category::factory()->create());

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSee('Open Trivia DB')
            ->assertSee('Not available on this server yet');
    });

    it('shows the question source to the other players', function () {
        $room = Room::factory()->withAdmin()->create(['question_source' => QuestionSource::OpenTdb]);
        $player = Player::factory()->for($room)->create();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSee('Question source')
            ->assertSee('Open Trivia DB');
    });

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
            ->assertSeeHtml('data-player-color="'.$player->color->value.'"')
            ->assertDontSee('Next question');
    });

    it('records the answer and skips the render while the round stays open', function () {
        [$room, $round, $player] = roomInPlay();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('answer', 1)
            ->assertReturned(1)
            ->assertRenderSkipped();

        expect($round->answers()->where('player_id', $player->id)->sole()->is_correct)->toBeTrue()
            ->and($round->fresh()->isRevealed())->toBeFalse();
    });

    it('shows the player their recorded answer and marks them as answered', function () {
        [$room, $round, $player] = roomInPlay();
        Answer::factory()->for($round)->for($player)->create(['choice' => 1]);

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSeeHtmlInOrder(['data-choice="1"', 'data-state="selected"'])
            ->assertSeeHtml('data-answered="true"')
            ->assertDontSee('Scores');
    });

    it('marks the correct answer and the chosen wrong answer after the reveal', function () {
        [$room, $round, $player] = roomInPlay();
        Livewire::actingAs($player, 'player')->test(RoomPage::class, ['room' => $room])->call('answer', 1);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('answer', 0)
            ->assertSeeHtmlInOrder(['data-choice="0"', 'data-state="wrong"'])
            ->assertSeeHtmlInOrder(['data-choice="1"', 'data-state="correct"'])
            ->assertSeeHtml('data-state="dim"');
    });

    it('shows who picked each answer after the reveal', function () {
        [$room, $round, $player] = roomInPlay();
        Livewire::actingAs($player, 'player')->test(RoomPage::class, ['room' => $room])->call('answer', 1);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('answer', 0)
            ->assertSeeHtmlInOrder(['Mars', 'data-picked-by', 'data-player-color="'.$player->color->value.'"']);
    });

    it('keeps the server state when the answer is rejected', function () {
        [$room, $round, $player] = roomInPlay();
        $round->update(['revealed_at' => now()]);

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('answer', 0)
            ->assertReturned(null)
            ->assertSeeHtmlInOrder(['data-choice="0"', 'data-state="dim"'])
            ->assertDontSeeHtml('data-state="selected"')
            ->assertDontSeeHtml('data-state="wrong"');

        expect($round->answers()->count())->toBe(0);
    });

    it('cues the right sound for each player after the reveal', function () {
        [$room, $round, $player] = roomInPlay();
        Livewire::actingAs($player, 'player')->test(RoomPage::class, ['room' => $room])->call('answer', 1);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('answer', 0)
            ->assertSeeHtml('data-sound-cue="wrong"')
            ->assertDontSeeHtml('data-sound-cue="correct"');

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSeeHtml('data-sound-cue="correct"')
            ->assertDontSeeHtml('data-sound-cue="wrong"');
    });

    it('does not cue a reveal sound while the round is open', function () {
        [$room, $round, $player] = roomInPlay();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('answer', 1)
            ->assertDontSeeHtml('data-sound-cue');
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
            ->assertSee('Next question')
            ->assertSeeHtml('advanceButton({ waitMs: 2000 })');

        expect($player->fresh()->score)->toBe(1000)
            ->and($room->admin->fresh()->score)->toBe(0);
    });

    it('shows how fast each player answered once the round is revealed', function () {
        freezeSecond();
        [$room, $round, $player] = roomInPlay();
        travel(4)->seconds();
        Livewire::actingAs($player, 'player')->test(RoomPage::class, ['room' => $room])->call('answer', 1);
        travel(2)->seconds();

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('answer', 0)
            ->assertSee('Scores')
            ->assertSeeInOrder(['Nikos', '4.0 s', 'Maria', '6.0 s']);
    });

    it('shows no answer time for answers recorded before speed was stored', function () {
        [$room, $round, $player] = roomInPlay();
        Answer::factory()->for($round)->for($player)->create(['elapsed_ms' => null, 'points' => 1000]);
        $round->update(['revealed_at' => now()]);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSee('Scores')
            ->assertSee('+1000')
            ->assertDontSee('0.0 s');
    });

    it('ignores time up before the timer has run out', function () {
        freezeSecond();
        [$room, $round, $player] = roomInPlay();
        travel(10)->seconds();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('timeUp')
            ->assertReturned(false)
            ->assertRenderSkipped();

        expect($round->fresh()->isRevealed())->toBeFalse();
    });

    it('reveals the round when a client reports that time is up', function () {
        freezeSecond();
        [$room, $round, $player] = roomInPlay();
        travel(21)->seconds();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('timeUp')
            ->assertReturned(true)
            ->assertSee('Scores')
            ->assertSee('Waiting for Maria to continue.');

        expect($round->fresh()->isRevealed())->toBeTrue();
    });

    it('renders the reveal when time is up after another player revealed the round', function () {
        [$room, $round, $player] = roomInPlay();
        $page = Livewire::actingAs($player, 'player')->test(RoomPage::class, ['room' => $room]);
        $round->update(['revealed_at' => now()]);

        $page->call('timeUp')
            ->assertReturned(true)
            ->assertSee('Scores');
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
        $nikos = Player::factory()->for($room)->create(['name' => 'Nikos', 'score' => 1200]);
        Round::factory()->for($room)->revealed()->create(['round_number' => 1]);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->call('nextRound')
            ->assertSee('Final results')
            ->assertSee('Nikos wins!')
            ->assertSeeInOrder(['Nikos', '1200', 'Maria', '800'])
            ->assertSeeHtml('data-player-color="'.$nikos->color->value.'"')
            ->assertSee('Play again');
    });

    it('credits open trivia db while playing its questions', function () {
        [$room, $round, $player] = roomInPlay();
        $room->update(['question_source' => QuestionSource::OpenTdb]);

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSee('Open Trivia Database');
    });

    it('does not credit open trivia db for the built-in questions', function () {
        [$room, $round, $player] = roomInPlay();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertDontSee('Open Trivia Database');
    });

    it('lets a player report the question, thanks them and shows it as reported', function () {
        [$room, $round, $player] = roomInPlay();

        Livewire::actingAs($player, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSee('Report question')
            ->call('reportQuestion')
            ->assertDispatched('toast-show', fn (string $event, array $params): bool => $params['slots']['text'] === 'Thanks! We will check this question.')
            ->assertSeeHtmlInOrder(['data-test="report-button"', 'Reported'])
            ->assertDontSee('Report question');

        expect($round->question->fresh()->reported_at)->not->toBeNull();
    });

    it('forbids a visitor who is not a player from reporting the question', function () {
        [$room, $round] = roomInPlay();

        Livewire::test(RoomPage::class, ['room' => $room])
            ->call('reportQuestion')
            ->assertForbidden();

        expect($round->question->fresh()->reported_at)->toBeNull();
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

    it('shows how many questions each player answered correctly', function () {
        $room = Room::factory()->finished()->withAdmin('Maria')->create(['total_rounds' => 2]);
        [$first, $second] = Round::factory()->for($room)->revealed()->count(2)->sequence(['round_number' => 1], ['round_number' => 2])->create();
        $nikos = Player::factory()->for($room)->create(['name' => 'Nikos', 'score' => 1500]);
        $eleni = Player::factory()->for($room)->create(['name' => 'Eleni', 'score' => 900]);
        $kostas = Player::factory()->for($room)->create(['name' => 'Kostas', 'score' => 400]);
        Answer::factory()->for($first)->for($nikos)->create(['is_correct' => true]);
        Answer::factory()->for($second)->for($nikos)->create(['is_correct' => true]);
        Answer::factory()->for($first)->for($eleni)->create(['is_correct' => true]);
        Answer::factory()->for($second)->for($eleni)->create(['is_correct' => false]);
        Answer::factory()->for($second)->for($kostas)->create(['is_correct' => true]);
        Answer::factory()->for($first)->for($room->admin)->create(['is_correct' => false]);

        Livewire::actingAs($room->admin, 'player')
            ->test(RoomPage::class, ['room' => $room])
            ->assertSeeInOrder([
                'Nikos', '1500', '2/2 correct',
                'Eleni', '900', '1/2 correct',
                'Kostas', '400', '1/2 correct',
                'Maria', '0/2 correct',
            ]);
    });
});
