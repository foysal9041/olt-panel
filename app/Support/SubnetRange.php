<?php

namespace App\Support;

/**
 * An IP subnet field can hold CIDR notation ("103.150.10.0/24") or a bare
 * IPv4 address (treated as a /32). Shared by IpPoolController and
 * NttnLinkController so both validate overlaps the same way.
 */
class SubnetRange
{
    /**
     * @return array{0: ?int, 1: ?int} [network, broadcast] as unsigned ints
     */
    public static function parse(string $subnet): array
    {
        $subnet = trim($subnet);

        if ($subnet === '') {
            return [null, null];
        }

        if (str_contains($subnet, '/')) {
            [$ip, $prefix] = array_pad(explode('/', $subnet, 2), 2, null);

            if (
                ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                || ! ctype_digit((string) $prefix)
                || (int) $prefix > 32
            ) {
                return [null, null];
            }

            $prefix = (int) $prefix;
            $ipLong = ip2long($ip);
            $maskLong = $prefix === 0 ? 0 : (~0 << (32 - $prefix)) & 0xFFFFFFFF;

            $network = $ipLong & $maskLong;
            $broadcast = $network | (~$maskLong & 0xFFFFFFFF);

            return [$network, $broadcast];
        }

        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($subnet);

            return [$ipLong, $ipLong];
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

    /**
     * Prefix length of "a.b.c.d/n" (a bare IP counts as /32), or null.
     */
    public static function prefix(string $subnet): ?int
    {
        [$min] = self::parse($subnet);

        if ($min === null) {
            return null;
        }

        return str_contains($subnet, '/') ? (int) explode('/', trim($subnet), 2)[1] : 32;
    }

    /**
     * Canonical CIDR with the real network address:
     * "103.161.2.50/30" -> "103.161.2.48/30". Null if not a valid IPv4 CIDR.
     */
    public static function normalize(string $subnet): ?string
    {
        [$min] = self::parse($subnet);
        $prefix = self::prefix($subnet);

        return $min === null ? null : long2ip($min) . '/' . $prefix;
    }

    /**
     * Split an address range into the fewest aligned CIDR blocks —
     * used to show free space as allocatable subnets.
     *
     * @return list<string>
     */
    public static function rangeToCidrs(int $start, int $end): array
    {
        $out = [];

        while ($start <= $end) {
            // Largest block aligned at $start...
            $size = $start === 0 ? 2 ** 32 : ($start & -$start);

            // ...that still fits before $end.
            while ($size > $end - $start + 1) {
                $size >>= 1;
            }

            $out[] = long2ip($start) . '/' . (32 - (int) log($size, 2));
            $start += $size;
        }

        return $out;
    }
}
