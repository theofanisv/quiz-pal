<?php

use function Pest\Laravel\get;

it('allows crawling, keeps the technical endpoints out and points to the sitemap', function () {
    get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *')
        ->assertSee('Disallow: /locale/')
        ->assertSee('Disallow: /broadcasting/')
        ->assertSee('Sitemap: '.route('sitemap'));
});
