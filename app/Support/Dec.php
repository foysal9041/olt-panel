<?php

namespace App\Support;

/**
 * Exact decimal arithmetic for money (bcmath), so nothing is lost to float
 * rounding along the way; values are rounded only when shown.
 */
class Dec
{
    public const SCALE = 6;

    public static function of(int|float|string|null $v): string
    {
        if ($v === null || $v === '') {
            return '0';
        }

        // Floats from a spreadsheet: their shortest form that reads back as the
        // same number, like Excel shows it (741873.93, not 741873.9300000001).
        if (is_float($v)) {
            $s = json_encode($v);
            if (stripos($s, 'e') !== false) {
                $s = rtrim(rtrim(sprintf('%.12F', $v), '0'), '.');
            }
        } else {
            $s = (string) $v;
        }

        return is_numeric($s) ? $s : '0';
    }

    public static function add(...$v): string
    {
        return array_reduce($v, fn ($sum, $x) => bcadd($sum, self::of($x), self::SCALE), '0');
    }

    public static function sub($a, $b): string
    {
        return bcsub(self::of($a), self::of($b), self::SCALE);
    }

    public static function mul($a, $b): string
    {
        return bcmul(self::of($a), self::of($b), self::SCALE);
    }

    public static function div($a, $b): string
    {
        return bccomp(self::of($b), '0', self::SCALE) === 0 ? '0' : bcdiv(self::of($a), self::of($b), self::SCALE);
    }

    /** Half away from zero, to $places decimals. */
    public static function round($v, int $places = 2): string
    {
        $v = self::of($v);
        $half = '0.' . str_repeat('0', $places) . '5';

        return bccomp($v, '0', self::SCALE) >= 0
            ? bcadd($v, $half, $places)
            : bcsub($v, $half, $places);
    }

    /** ৳81,198.02 (negative as −৳40,599.01). */
    public static function taka($v, bool $sign = false): string
    {
        $r = self::round($v, 2);
        $neg = str_starts_with($r, '-');
        $abs = ltrim($r, '-');
        [$int, $frac] = array_pad(explode('.', $abs), 2, '00');
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $int);

        return ($neg ? '−' : ($sign ? '+' : '')) . '৳' . $int . '.' . str_pad($frac, 2, '0');
    }

    /** Lakh/crore grouping as in the office's sheets: 31,53,035.24 (negative as −…). */
    public static function lakh($v): string
    {
        $r = self::round($v, 2);
        $neg = str_starts_with($r, '-');
        [$int, $frac] = array_pad(explode('.', ltrim($r, '-')), 2, '00');

        if (strlen($int) > 3) {
            $head = substr($int, 0, -3);
            $int = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $head) . ',' . substr($int, -3);
        }

        return ($neg ? '−' : '') . $int . '.' . str_pad($frac, 2, '0');
    }

    /** 81198.02 as a float for spreadsheets (already rounded to 2 places). */
    public static function toFloat($v): float
    {
        return (float) self::round($v, 2);
    }

    public static function isNegative($v): bool
    {
        return bccomp(self::of($v), '0', self::SCALE) < 0;
    }
}
