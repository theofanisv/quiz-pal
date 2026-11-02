<?php

it('passes the browser-side reverb settings to the page', function () {
    $client = [
        'key' => 'browser-key',
        'host' => 'quiz.example.com',
        'port' => 443,
        'scheme' => 'https',
    ];
    config(['broadcasting.connections.reverb.client' => $client]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<meta name="reverb" content="'.e(json_encode($client)).'">', false);
});
