<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apply the locale the visitor chose (stored in the session) to the current request.
 */
class SetLocale
{
    public const string SESSION_KEY = 'locale';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locale::tryFrom((string) $request->session()->get(self::SESSION_KEY, ''));

        if ($locale !== null) {
            app()->setLocale($locale->value);
        }

        return $next($request);
    }
}
