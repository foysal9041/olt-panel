<?php

namespace App\Support;

/**
 * The interface language (English / বাংলা). Templates' text goes through
 * Ui::t() (see BladeTextWrapper); the dictionaries are lang/ui/bn.json
 * (English → Bangla) and lang/ui/en.json (the Bangla written in templates
 * → English). Anything not in the dictionary shows as written.
 */
class Ui
{
    public const LOCALES = ['en' => 'English', 'bn' => 'বাংলা'];

    /** @var array<string, array<string, string>> */
    protected static array $dict = [];

    public static function t(?string $text): string
    {
        return self::in(app()->getLocale(), $text);
    }

    /** English whatever the interface language — for stored labels and printouts. */
    public static function english(?string $text): string
    {
        return self::in('en', $text);
    }

    public static function in(string $locale, ?string $text): string
    {
        if ($text === null || $text === '') {
            return (string) $text;
        }

        $dict = self::$dict[$locale] ??= self::load($locale);
        $key = self::key($text);

        if (isset($dict[$key])) {
            return $dict[$key];
        }

        // Built-up text ("Petty cash book — রবিবার, 03/10/2026"): translate the parts.
        if (preg_match('/ (—|·|\|) /u', $key)) {
            $parts = preg_split('/( — | · | \| )/u', $key, -1, PREG_SPLIT_DELIM_CAPTURE);
            $hit = false;
            foreach ($parts as $i => $part) {
                if ($i % 2 === 0 && isset($dict[$part])) {
                    $parts[$i] = $dict[$part];
                    $hit = true;
                }
            }
            if ($hit) {
                return implode('', $parts);
            }
        }

        return $text;
    }

    /** Same text, however it was spread over lines in the template. */
    public static function key(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Day name in the interface language: Saturday / শনিবার. */
    public static function day(\DateTimeInterface $date): string
    {
        return self::isBangla() ? Bangla::day(\Illuminate\Support\Carbon::instance($date)) : $date->format('l');
    }

    /** Month name in the interface language: October / অক্টোবর. */
    public static function month(\DateTimeInterface $date): string
    {
        return self::isBangla() ? Bangla::month(\Illuminate\Support\Carbon::instance($date)) : $date->format('F');
    }

    /** "3 October 2026" / "৩ অক্টোবর ২০২৬" */
    public static function longDate(\DateTimeInterface $date): string
    {
        return self::isBangla()
            ? Bangla::digits($date->format('j')) . ' ' . self::month($date) . ' ' . Bangla::digits($date->format('Y'))
            : $date->format('j F Y');
    }

    public static function isBangla(): bool
    {
        return app()->getLocale() === 'bn';
    }

    /** Forget loaded dictionaries (after editing them). */
    public static function flush(): void
    {
        self::$dict = [];
    }

    protected static function load(string $locale): array
    {
        $file = lang_path("ui/{$locale}.json");

        return is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
    }
}
