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
}
