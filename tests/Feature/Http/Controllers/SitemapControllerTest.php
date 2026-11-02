<?php

use function Pest\Laravel\get;

it('lists every language version of the home page with its alternates', function () {
    get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('<loc>'.route('home').'</loc>', false)
        ->assertSee('<loc>'.route('home.localized', 'el').'</loc>', false)
        ->assertSee('<xhtml:link rel="alternate" hreflang="en" href="'.route('home').'"/>', false)
        ->assertSee('<xhtml:link rel="alternate" hreflang="el" href="'.route('home.localized', 'el').'"/>', false)
        ->assertSee('<xhtml:link rel="alternate" hreflang="x-default" href="'.route('home').'"/>', false);
});
