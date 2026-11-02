<?php

use function Pest\Laravel\get;
use function Pest\Laravel\withSession;

it('renders the root url in the default language even when the session chose another', function () {
    withSession(['locale' => 'el'])
        ->get('/')
        ->assertOk()
        ->assertSee('Quiz night with friends')
        ->assertDontSee('Βραδιά κουίζ με φίλους')
        ->assertSessionHas('locale', 'en');
});

it('renders the home page in greek at its own url and remembers the choice', function () {
    get('/el')
        ->assertOk()
        ->assertSee('Βραδιά κουίζ με φίλους')
        ->assertSee('<a href="'.route('home.localized', 'el').'" data-test="home-link"', false)
        ->assertSessionHas('locale', 'el');
});

it('redirects the default language prefix to the root url permanently', function () {
    get('/en')
        ->assertStatus(301)
        ->assertRedirect(route('home'));
});

it('returns 404 for a language prefix the app does not support', function () {
    get('/fr')->assertNotFound();
});
