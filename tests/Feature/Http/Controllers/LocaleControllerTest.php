<?php

use App\Livewire\Game\Room as RoomPage;
use App\Models\Question;
use App\Models\Room;
use App\Models\Round;
use Livewire\Livewire;

use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\withSession;

it('remembers the chosen language and returns to the previous page', function () {
    from(route('home'))
        ->get(route('locale.switch', 'el'))
        ->assertRedirect(route('home'))
        ->assertSessionHas('locale', 'el');
});

it('rejects a language the app does not support', function () {
    get('/locale/fr')->assertNotFound();
});

it('renders the interface in greek once chosen', function () {
    withSession(['locale' => 'el'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Βραδιά κουίζ με φίλους')
        ->assertDontSee('Quiz night with friends');
});

it('shows the question in greek to a greek-speaking player', function () {
    $room = Room::factory()->playing()->withAdmin()->create();
    $question = Question::factory()->create(['text_el' => 'Ποιος πλανήτης είναι γνωστός ως ο Κόκκινος Πλανήτης;']);
    Round::factory()->for($room)->for($question)->started()->create();
    app()->setLocale('el');

    Livewire::actingAs($room->admin, 'player')
        ->test(RoomPage::class, ['room' => $room])
        ->assertSee('Ποιος πλανήτης είναι γνωστός ως ο Κόκκινος Πλανήτης;')
        ->assertSee('Χρόνος');
});

it('shows validation messages in greek', function () {
    $room = Room::factory()->withAdmin()->create();
    app()->setLocale('el');

    Livewire::test(RoomPage::class, ['room' => $room])
        ->set('name', 'x')
        ->call('join')
        ->assertSee('Το πεδίο «όνομα» πρέπει να έχει τουλάχιστον 2 χαρακτήρες.');
});
