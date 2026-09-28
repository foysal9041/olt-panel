<?php

namespace App\Support;

/**
 * Spells out a Taka amount for the "Amount in Words" line on printed
 * invoices/receipts, using the lakh/crore grouping conventional in
 * Bangladesh rather than the western thousand/million grouping.
 */
class NumberToWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    public static function taka(float $amount): string
    {
        $taka = (int) floor($amount);
        $paisa = (int) round(($amount - $taka) * 100);

        $words = 'Taka ' . (self::convert($taka) ?: 'Zero');

        if ($paisa > 0) {
            $words .= ' and ' . self::convert($paisa) . ' Paisa';
        }

        return $words . ' Only';
    }

    private static function convert(int $number): string
    {
        if ($number === 0) {
            return '';
        }

        $crore = intdiv($number, 10000000);
        $number %= 10000000;
        $lakh = intdiv($number, 100000);
        $number %= 100000;
        $thousand = intdiv($number, 1000);
        $number %= 1000;
        $hundred = intdiv($number, 100);
        $rest = $number % 100;

        $parts = [];

        if ($crore) {
            $parts[] = self::twoDigits($crore) . ' Crore';
        }

        if ($lakh) {
            $parts[] = self::twoDigits($lakh) . ' Lakh';
        }

        if ($thousand) {
            $parts[] = self::twoDigits($thousand) . ' Thousand';
        }

        if ($hundred) {
            $parts[] = self::ONES[$hundred] . ' Hundred';
        }

        if ($rest) {
            $parts[] = self::twoDigits($rest);
        }

        return implode(' ', $parts);
    }

    private static function twoDigits(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }

        $tens = intdiv($n, 10);
        $ones = $n % 10;

        return trim(self::TENS[$tens] . ' ' . self::ONES[$ones]);
    }
}
