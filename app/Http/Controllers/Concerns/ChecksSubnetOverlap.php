<?php

namespace App\Http\Controllers\Concerns;

use App\Models\IpPool;
use App\Models\NttnLink;
use App\Support\SubnetRange;
use Illuminate\Validation\ValidationException;

/**
 * IP subnets are recorded in two places — IP Management (IpPool::subnet)
 * and each NTTN link's public/private IP subnet fields. IpPoolController
 * and NttnLinkController both need to reject a submitted subnet that
 * overlaps anything already recorded on either side.
 */
trait ChecksSubnetOverlap
{
    private function assertSubnetNotOverlapping(
        string $subnet,
        ?int $excludeIpPoolId = null,
        ?int $excludeNttnLinkId = null,
        string $fieldName = 'subnet'
    ): void {
        $subnet = trim($subnet);

        if ($subnet === '') {
            return;
        }

        [$min, $max] = SubnetRange::parse($subnet);

        if ($min === null) {
            return;
        }

        $pools = IpPool::whereNotNull('subnet')
            ->where('subnet', '!=', '')
            ->when($excludeIpPoolId, fn ($q) => $q->where('id', '!=', $excludeIpPoolId))
            ->get(['id', 'subnet']);

        foreach ($pools as $pool) {
            if (SubnetRange::overlaps($subnet, $pool->subnet)) {
                throw ValidationException::withMessages([
                    $fieldName => "Subnet {$subnet} overlaps with IP Management subnet {$pool->subnet}.",
                ]);
            }
        }

        $links = NttnLink::where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('public_ip_subnet')->where('public_ip_subnet', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('private_ip_subnet')->where('private_ip_subnet', '!=', '');
                });
            })
            ->when($excludeNttnLinkId, fn ($q) => $q->where('id', '!=', $excludeNttnLinkId))
            ->get(['id', 'link_id', 'public_ip_subnet', 'private_ip_subnet']);

        foreach ($links as $link) {
            $candidates = [
                'public' => $link->public_ip_subnet,
                'private' => $link->private_ip_subnet,
            ];

            foreach ($candidates as $type => $value) {
                if ($value && SubnetRange::overlaps($subnet, $value)) {
                    throw ValidationException::withMessages([
                        $fieldName => "Subnet {$subnet} overlaps with the {$type} subnet ({$value}) of NTTN link \"{$link->link_id}\".",
                    ]);
                }
            }
        }
    }
}
