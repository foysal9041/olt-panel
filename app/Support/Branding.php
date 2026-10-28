<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The company this installation belongs to: its names, contact details,
 * logos and colours. One installation = one company; it is set under
 * Settings → Company Branding (app_settings "branding", logos in
 * storage/app/branding) over the defaults in config/branding.php.
 *
 * Without an uploaded logo, a plain one is drawn from the company's name
 * and colour (BrandAssets), so a new install never shows another company.
 */
class Branding
{
    public const FILES = ['logo', 'icon', 'favicon'];

    /** The design's own colours, used where a colour is left empty. */
    public const DESIGN = [
        'primary' => '#4f46e5',
        'secondary' => '#0f172a',
        'accent' => '#6366f1',
        'background' => '#f4f6fb',
        'login' => '#071a33',
        'login_accent' => '#0ea5e9',
    ];

    protected static ?array $settings = null;

    public static function all(): array
    {
        if (static::$settings === null) {
            $defaults = config('branding.defaults', []);
            try {
                static::$settings = AppSetting::get('branding', $defaults);
            } catch (\Throwable) {
                static::$settings = $defaults; // no database yet (installing)
            }
        }

        return static::$settings;
    }

    public static function get(string $key, $default = null)
    {
        $v = static::all()[$key] ?? null;

        return filled($v) ? $v : $default;
    }

    /** Forget what was read (after saving, and between test runs). */
    public static function flush(): void
    {
        static::$settings = null;
    }

    // ---- Names --------------------------------------------------------------

    /** Short name, e.g. "Acme Fiber". */
    public static function name(): string
    {
        return (string) static::get('name', 'OLT Panel');
    }

    /** Name on documents, e.g. "Acme Fiber Ltd". */
    public static function legalName(): string
    {
        return (string) static::get('legal_name', static::name());
    }

    /** Browser title and the app's own name, e.g. "Acme Fiber ERP". */
    public static function appTitle(): string
    {
        return (string) static::get('app_title', static::name() . ' ERP');
    }

    public static function tagline(): string
    {
        return (string) static::get('tagline', 'Network Operations & Business Panel');
    }

    /** ID-card wordmark: the legal name's first word, then the rest ("ACME" / "FIBER LTD"). */
    public static function cardWords(): array
    {
        $words = preg_split('/\s+/', trim(static::legalName()), 2);

        return [Str::upper($words[0] ?? ''), Str::upper($words[1] ?? '')];
    }

    // ---- Contact ------------------------------------------------------------

    public static function email(): ?string
    {
        return static::get('email');
    }

    public static function accountsEmail(): ?string
    {
        return static::get('accounts_email', static::email());
    }

    public static function phone(): ?string
    {
        return static::get('phone');
    }

    /** As entered, e.g. "www.example.com". */
    public static function website(): ?string
    {
        return static::get('website');
    }

    /** Without scheme and "www.", e.g. "example.com". */
    public static function websiteShort(): ?string
    {
        $w = static::website();

        return $w ? preg_replace('#^(https?://)?(www\.)?#i', '', rtrim($w, '/')) : null;
    }

    public static function websiteUrl(): ?string
    {
        $w = static::website();

        return $w ? (preg_match('#^https?://#i', $w) ? $w : 'https://' . $w) : null;
    }

    /** @return list<string> */
    public static function addressLines(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) static::get('address', ''))), 'strlen'));
    }

    /** The address on one line. */
    public static function address(): string
    {
        return implode(', ', static::addressLines());
    }

    /** Letterhead footer: "<legal name>, <first line>" then the other lines, one per line. */
    public static function letterheadAddress(): HtmlString
    {
        $lines = static::addressLines();
        $first = static::legalName() . ($lines ? ', ' . array_shift($lines) : '');

        return new HtmlString(implode('<br>', array_map('e', array_merge([$first], $lines))));
    }

    /** Area only, for small letterheads (default: the address's last line). */
    public static function addressShort(): string
    {
        $lines = static::addressLines();

        return (string) static::get('address_short', end($lines) ?: '');
    }

    // ---- Documents and footer ---------------------------------------------------

    /** Start of invoice numbers, e.g. "INV/" → INV/OCT26/01. */
    public static function invoicePrefix(): string
    {
        return (string) static::get('invoice_prefix', 'INV/');
    }

    public static function copyrightHolder(): string
    {
        return (string) static::get('copyright_holder', static::legalName());
    }

    /** The footer's second line; *word* is shown bold. Escaped, so no HTML gets in. */
    public static function footerCredit(): ?HtmlString
    {
        $text = static::get('footer_credit');

        return $text ? new HtmlString(preg_replace('/\*([^*]+)\*/', '<strong>$1</strong>', e($text))) : null;
    }

    /** @return array<string, string> label => url */
    public static function legalLinks(): array
    {
        return array_filter([
            'Privacy Policy' => static::safeUrl(static::get('privacy_url')),
            'Terms of Service' => static::safeUrl(static::get('terms_url')),
        ]);
    }

    protected static function safeUrl(?string $url): ?string
    {
        return $url && preg_match('#^https?://#i', $url) ? $url : null;
    }

    // ---- Images -------------------------------------------------------------------

    /** An uploaded file's path, or null when the company hasn't uploaded one. */
    public static function uploaded(string $which): ?string
    {
        $file = static::get($which);
        $path = $file ? storage_path('app/branding/' . basename($file)) : null;

        return $path && is_file($path) ? $path : null;
    }

    public static function hasLogo(): bool
    {
        return static::uploaded('logo') !== null;
    }

    /** File on disk — for PDFs and mail. which: logo | icon | icon-gray | favicon */
    public static function path(string $which): string
    {
        return app(\App\Services\BrandAssets::class)->path($which);
    }

    /** Public URL with a cache-buster. */
    public static function url(string $which): string
    {
        $path = static::path($which);

        return route('brand.asset', $which) . '?v=' . substr(md5($path . '|' . @filemtime($path)), 0, 10);
    }

    // ---- Colours ------------------------------------------------------------------

    public static function color(string $which): ?string
    {
        $c = static::get($which . '_color');

        return is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : null;
    }

    /** The colour in use: the company's, else the design's. */
    public static function colorOrDesign(string $which): string
    {
        return static::color($which) ?? self::DESIGN[$which];
    }

    /**
     * CSS variables for the company's colours, only for those that are set:
     * with none set the page is exactly the original design.
     */
    public static function panelCss(): string
    {
        $css = [];
        if ($c = static::color('primary')) {
            $css[] = "--brand: $c; --brand-dark: " . static::mix($c, '#000000', .16) . '; --brand-light: ' . static::mix($c, '#ffffff', .92) . ';';
        }
        if ($c = static::color('secondary')) {
            $css[] = '--sidebar-1: ' . static::mix($c, '#000000', .25) . "; --sidebar-2: $c; --sidebar-3: " . static::mix($c, '#ffffff', .05) . ';';
        }
        if ($c = static::color('accent')) {
            $css[] = "--accent-color: $c;";
        }
        if ($c = static::color('background')) {
            $css[] = "--surface: $c;";
        }

        return $css ? ':root { ' . implode(' ', $css) . ' }' : '';
    }

    /** The same for the sign-in page (css/auth.css). */
    public static function authCss(): string
    {
        $css = [];
        if ($c = static::color('secondary')) {
            $css[] = '--auth-navy: ' . static::mix($c, '#000000', .2) . "; --auth-navy-2: $c;";
        }
        if ($c = static::color('accent') ?? static::color('primary')) {
            $css[] = "--auth-sky: $c; --auth-sky-deep: " . static::mix($c, '#000000', .3) . '; --auth-sky-soft: ' . static::mix($c, '#ffffff', .88) . ';';
        }

        return $css ? ':root { ' . implode(' ', $css) . ' }' : '';
    }

    /** The browser's theme colour (mobile address bar). */
    public static function themeColor(): string
    {
        return static::color('secondary') ?? self::DESIGN['login'];
    }

    /** $a moved $t (0–1) of the way to $b. */
    public static function mix(string $a, string $b, float $t): string
    {
        [$r1, $g1, $b1] = sscanf($a, '#%02x%02x%02x');
        [$r2, $g2, $b2] = sscanf($b, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', round($r1 + ($r2 - $r1) * $t), round($g1 + ($g2 - $g1) * $t), round($b1 + ($b2 - $b1) * $t));
    }

    // ---- AdminLTE -----------------------------------------------------------------

    /** Point the admin layout's title, logos and preloader at the company's. */
    public static function applyToAdminLte(): void
    {
        $relative = fn (string $which) => ltrim(parse_url(static::url($which), PHP_URL_PATH), '/') . '?' . parse_url(static::url($which), PHP_URL_QUERY);
        config([
            'adminlte.title' => static::appTitle(),
            'adminlte.logo_img' => $relative('icon'),
            'adminlte.logo_img_alt' => static::name(),
            'adminlte.preloader.img.path' => $relative('logo'),
            'adminlte.preloader.img.alt' => static::name(),
        ]);
        // Without an uploaded wide logo the sidebar shows the mark and the name as text (readable on its dark background).
        if (static::hasLogo()) {
            config(['adminlte.logo_img_xl' => $relative('logo'), 'adminlte.logo' => '']);
        } else {
            config(['adminlte.logo_img_xl' => null, 'adminlte.logo' => '<b>' . e(static::name()) . '</b>']);
        }
    }
}
