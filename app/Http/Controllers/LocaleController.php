<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Remember the chosen locale for this visitor and return to the previous page.
     */
    public function __invoke(Request $request, Locale $locale): RedirectResponse
    {
        $request->session()->put(SetLocale::SESSION_KEY, $locale->value);

        return redirect()->back(fallback: route('home'));
    }
}
