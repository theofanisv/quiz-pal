<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /**
     * Let crawlers in everywhere except the endpoints that only make sense inside a browser session.
     * Pages that must stay out of the index say so themselves with a robots meta tag.
     */
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /locale/',
            'Disallow: /broadcasting/',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", headers: ['Content-Type' => 'text/plain']);
    }
}
