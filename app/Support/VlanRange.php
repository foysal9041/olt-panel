<?php

namespace App\Support;

/**
 * A VLAN field can hold a single number ("20") or a range ("10-15").
 * Shared by OltController and VlanController so both validate overlaps
 * the same way.
 */
class VlanRange
{
    /**
     * @return array{0: ?int, 1: ?int}
     */
    public static function parse(string $vlan): array
    {
        $vlan = trim($vlan);

        if (preg_match('/^(\d+)-(\d+)$/', $vlan, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        if (preg_match('/^\d+$/', $vlan)) {
            return [(int) $vlan, (int) $vlan];
        }

        return [null, null];
    }

    /**
     * A comma-separated list ("2211-2214, 2435-2439, 20") as [min, max]
     * pairs; anything unreadable is skipped. Reversed ranges are put right.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function parseList(?string $value): array
    {
        $ranges = [];

        foreach (preg_split('/\s*,\s*/', trim((string) $value)) as $part) {
            [$min, $max] = self::parse(preg_replace('/\s*-\s*/', '-', $part));

            if ($min !== null) {
                $ranges[] = [min($min, $max), max($min, $max)];
            }
        }

        return $ranges;
    }

    /**
     * [2505, 2508] -> "2505-2508", [20, 20] -> "20".
     */
    public static function format(int $min, int $max): string
    {
        return $min === $max ? (string) $min : "{$min}-{$max}";
    }

    public static function overlaps(string $a, string $b): bool
    {
        [$aMin, $aMax] = self::parse($a);
        [$bMin, $bMax] = self::parse($b);

        if ($aMin === null || $bMin === null) {
            return false;
        }

        return $aMin <= $bMax && $aMax >= $bMin;
    }
}
