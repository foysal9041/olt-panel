<?php

namespace App\Http\Middleware;

use App\Support\Ui;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface language: the signed-in user's choice, else the session's,
 * else the "locale" cookie (so the sign-in page remembers it), else English.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? $request->cookie('locale');

        if (isset(Ui::LOCALES[$locale])) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
