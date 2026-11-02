<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * The only indexable pages are the home pages, one per language, each pointing at the others.
     */
    public function __invoke(): Response
    {
        $homeUrls = [];

        foreach (Locale::cases() as $locale) {
            $homeUrls[$locale->value] = $locale->homeUrl();
        }

        $xml = view('sitemap', [
            'homeUrls' => $homeUrls,
            'defaultHomeUrl' => Locale::default()->homeUrl(),
        ])->render();

        return response('<?xml version="1.0" encoding="UTF-8"?>'."\n".$xml, headers: ['Content-Type' => 'application/xml']);
    }
}
