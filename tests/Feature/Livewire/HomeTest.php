<?php

use App\Livewire\Home;
use App\Models\Category;
use App\Models\Room;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

use function Pest\Laravel\get;

it('renders the create and join forms', function () {
    get(route('home'))
        ->assertOk()
        ->assertSeeLivewire(Home::class)
        ->assertSee('Create a room')
        ->assertSee('Join a room');
});

it('creates a room, signs the creator in as its admin and opens the lobby', function () {
    Category::factory()->create();

    $component = Livewire::test(Home::class)
        ->set('name', 'Maria')
        ->call('create');

    $room = Room::query()->sole();
    $component->assertRedirect(route('rooms.show', $room));
    expect($room->admin->name)->toBe('Maria')
        ->and(Auth::guard('player')->id())->toBe($room->admin->id);
});

it('shows a validation error instead of creating a room when the name is missing', function () {
    Livewire::test(Home::class)
        ->set('name', '')
        ->call('create')
        ->assertHasErrors(['name' => 'required'])
        ->assertNoRedirect();

    expect(Room::query()->count())->toBe(0);
});

it('opens an existing room from a code typed in lowercase', function () {
    $room = Room::factory()->create(['code' => 'KXWP']);

    Livewire::test(Home::class)
        ->set('code', ' kxwp ')
        ->call('join')
        ->assertRedirect(route('rooms.show', $room));
});

it('shows an error for a code that does not exist', function () {
    Livewire::test(Home::class)
        ->set('code', 'ZZZZ')
        ->call('join')
        ->assertHasErrors('code')
        ->assertSee('No room was found with that code.')
        ->assertNoRedirect();
});

it('links the web app manifest and theme colour', function () {
    get(route('home'))
        ->assertSee('<link rel="manifest" href="/site.webmanifest">', false)
        ->assertSee('<meta name="theme-color" content="#0b1020">', false);
});
