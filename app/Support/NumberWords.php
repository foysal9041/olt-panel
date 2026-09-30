<?php

namespace App\Support;

/**
 * Money in words, Bangladeshi style (crore / lakh / thousand):
 * 393417.53 → "Taka Three Lakh Ninety-Three Thousand Four Hundred Seventeen and Paisa Fifty-Three Only".
 */
class NumberWords
{
    protected const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    protected const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    public static function taka($amount): string
    {
        $r = Dec::round($amount, 2);
        $negative = str_starts_with($r, '-');
        [$int, $frac] = array_pad(explode('.', ltrim($r, '-')), 2, '00');

        $words = 'Taka ' . (((int) $int) === 0 ? 'Zero' : self::whole($int));
        if ((int) $frac > 0) {
            $words .= ' and Paisa ' . self::below100((int) $frac);
        }

        return ($negative ? 'Minus ' : '') . $words . ' Only';
    }

    /**
     * International style, as on the office's invoices:
     * 391442 → "Three Hundred Ninety-One Thousand Four Hundred Forty-Two Taka Only"
     * (paisa added when there are any: "… Taka and Fifty-Three Paisa Only").
     */
    public static function international($amount): string
    {
        $r = Dec::round($amount, 2);
        $negative = str_starts_with($r, '-');
        [$int, $frac] = array_pad(explode('.', ltrim($r, '-')), 2, '00');

        $words = (((int) $int) === 0 ? 'Zero' : self::groups($int)) . ' Taka';
        if ((int) $frac > 0) {
            $words .= ' and ' . self::below100((int) $frac) . ' Paisa';
        }

        return ($negative ? 'Minus ' : '') . $words . ' Only';
    }

    /** Whole number in billion / million / thousand / hundred. */
    protected static function groups(string $n): string
    {
        $n = ltrim($n, '0') ?: '0';
        $labels = ['', ' Thousand', ' Million', ' Billion', ' Trillion'];
        $chunks = array_reverse(str_split(strrev($n), 3));
        $parts = [];

        foreach ($chunks as $i => $chunk) {
            $value = (int) strrev($chunk);
            $label = $labels[count($chunks) - 1 - $i] ?? '';
            if ($value > 0) {
                $words = [];
                if ($value >= 100) {
                    $words[] = self::ONES[intdiv($value, 100)] . ' Hundred';
                }
                if ($value % 100) {
                    $words[] = self::below100($value % 100);
                }
                $parts[] = implode(' ', $words) . $label;
            }
        }

        return implode(' ', $parts);
    }

    /** Whole number (as a digit string) in crore / lakh / thousand / hundred. */
    protected static function whole(string $n): string
    {
        $n = ltrim($n, '0') ?: '0';
        $parts = [];

        // Crores can themselves run into lakhs etc. (e.g. 125 crore).
        if (strlen($n) > 7) {
            $parts[] = self::whole(substr($n, 0, -7)) . ' Crore';
            $n = substr($n, -7);
        }

        $n = (int) $n;
        foreach ([[100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$size, $label]) {
            if ($n >= $size) {
                $parts[] = self::below100(intdiv($n, $size)) . " {$label}";
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = self::below100($n);
        }

        return implode(' ', $parts);
    }

    protected static function below100(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }

        return self::TENS[intdiv($n, 10)] . ($n % 10 ? '-' . self::ONES[$n % 10] : '');
    }
}
