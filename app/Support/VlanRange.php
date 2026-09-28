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
