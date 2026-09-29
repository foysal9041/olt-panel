<?php

namespace App\Http\Controllers\Concerns;

use App\Services\VlanInventory;
use App\Support\VlanRange;
use Illuminate\Validation\ValidationException;

/**
 * VLANs are recorded in four places — the VLAN each OLT is configured on
 * (Olt::vlan), the entries in VLAN Management (the Vlan model), each NTTN
 * link's peering VLAN (NttnLink::peering_vlan) and the VLANs on IP pools
 * (IpPool::vlan, a list like "2211-2214, 2435-2439"). Saving any of them
 * rejects a VLAN that's already recorded anywhere else, so no VLAN is
 * given out twice. VLANs are compared within one network: the core, or
 * the record's POP when that zone has its own VLANs ($zone). And an OLT,
 * pool or NTTN link may take VLANs *reserved* in VLAN Management
 * ($reservedIsFree) — that's what reserving them was for.
 */
trait ChecksVlanOverlap
{
    private function assertVlanNotOverlapping(
        string $vlan,
        ?int $excludeOltId = null,
        ?int $excludeVlanId = null,
        ?int $excludeNttnLinkId = null,
        string $fieldName = 'vlan',
        ?int $excludeIpPoolId = null,
        bool $reservedIsFree = true,
        ?string $zone = null
    ): void {
        $vlan = trim($vlan);

        if ($vlan === '') {
            return;
        }

        foreach (VlanRange::parseList($vlan) as [$min, $max]) {
            if ($min < VlanInventory::MIN || $max > VlanInventory::MAX) {
                throw ValidationException::withMessages([
                    $fieldName => 'VLAN IDs must be between ' . VlanInventory::MIN . ' and ' . VlanInventory::MAX . '.',
                ]);
            }
        }

        $except = array_filter([
            'olt' => $excludeOltId,
            'vlan' => $excludeVlanId,
            'nttn' => $excludeNttnLinkId,
            'pool' => $excludeIpPoolId,
        ]);

        $inventory = new VlanInventory;
        $network = $inventory->networkFor($zone);
        $conflicts = $inventory->conflicts($vlan, $except, $reservedIsFree, $network);

        if ($conflicts) {
            throw ValidationException::withMessages([
                $fieldName => collect($conflicts)->take(3)->map(function ($c) {
                    $what = VlanInventory::SOURCES[$c['source']] . ($c['reserved'] ? ' (reserved)' : '');
                    $theirs = VlanRange::format($c['from'], $c['to']);

                    return "VLAN {$c['overlap']} is already used by {$what} \"{$c['label']}\" (VLAN {$theirs}).";
                })->implode(' ')
                    . (count($conflicts) > 3 ? ' …and ' . (count($conflicts) - 3) . ' more.' : '')
                    . ($network ? " ({$network} has its own VLANs.)" : '') . ' Use VLAN Management → Find Free VLANs to get unused ones.',
            ]);
        }
    }
}
