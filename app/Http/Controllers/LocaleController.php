<?php

namespace App\Http\Controllers;

use App\Support\Ui;
use Illuminate\Http\Request;

/** English / বাংলা switch: remembered on the user, in the session and in a cookie. */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale)
    {
        if ($locale === 'toggle') {
            $locale = app()->getLocale() === 'bn' ? 'en' : 'bn';
        }
        abort_unless(isset(Ui::LOCALES[$locale]), 404);

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->saveQuietly();

        return redirect()->back(fallback: url('/'))->withCookie(cookie()->forever('locale', $locale));
    }
}
