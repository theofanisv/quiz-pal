<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apply the visitor's language: the one named by the home page URL, otherwise the one stored in the session.
 */
class SetLocale
{
    public const string SESSION_KEY = 'locale';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->localeFromHomeUrl($request)
            ?? Locale::tryFrom((string) $request->session()->get(self::SESSION_KEY, ''));

        if ($locale !== null) {
            app()->setLocale($locale->value);
        }

        return $next($request);
    }

    /**
     * Each language has its own home page URL. Opening one is a choice that the pages after it keep.
     */
    private function localeFromHomeUrl(Request $request): ?Locale
    {
        if ($request->routeIs('home')) {
            $locale = Locale::default();
        } elseif ($request->routeIs('home.localized')) {
            $locale = Locale::from((string) $request->route('locale'));
        } else {
            return null;
        }

        $request->session()->put(self::SESSION_KEY, $locale->value);

        return $locale;
    }
}
