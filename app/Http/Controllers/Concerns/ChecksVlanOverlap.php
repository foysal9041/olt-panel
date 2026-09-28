<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Olt;
use App\Models\NttnLink;
use App\Models\Vlan;
use App\Support\VlanRange;
use Illuminate\Validation\ValidationException;

/**
 * VLANs are recorded in three places — the VLAN each OLT is configured on
 * (Olt::vlan), the reserved/planned entries in VLAN Management (the Vlan
 * model), and each NTTN link's peering VLAN (NttnLink::peering_vlan).
 * OltController, VlanController and NttnLinkController all need to reject a
 * submitted VLAN that overlaps anything already recorded anywhere else.
 */
trait ChecksVlanOverlap
{
    private function assertVlanNotOverlapping(
        string $vlan,
        ?int $excludeOltId = null,
        ?int $excludeVlanId = null,
        ?int $excludeNttnLinkId = null,
        string $fieldName = 'vlan'
    ): void {
        $vlan = trim($vlan);

        if ($vlan === '') {
            return;
        }

        [$min, $max] = VlanRange::parse($vlan);

        if ($min === null) {
            return;
        }

        $olts = Olt::whereNotNull('vlan')
            ->where('vlan', '!=', '')
            ->when($excludeOltId, fn ($q) => $q->where('id', '!=', $excludeOltId))
            ->get(['id', 'name', 'vlan']);

        foreach ($olts as $olt) {
            if (VlanRange::overlaps($vlan, $olt->vlan)) {
                throw ValidationException::withMessages([
                    $fieldName => "VLAN {$vlan} overlaps with OLT \"{$olt->name}\" which already uses VLAN {$olt->vlan}.",
                ]);
            }
        }

        $reserved = Vlan::whereNotNull('vlan')
            ->where('vlan', '!=', '')
            ->when($excludeVlanId, fn ($q) => $q->where('id', '!=', $excludeVlanId))
            ->get(['id', 'name', 'vlan']);

        foreach ($reserved as $entry) {
            if (VlanRange::overlaps($vlan, $entry->vlan)) {
                throw ValidationException::withMessages([
                    $fieldName => "VLAN {$vlan} overlaps with reserved VLAN \"{$entry->name}\" ({$entry->vlan}) in VLAN Management.",
                ]);
            }
        }

        $nttnLinks = NttnLink::whereNotNull('peering_vlan')
            ->where('peering_vlan', '!=', '')
            ->when($excludeNttnLinkId, fn ($q) => $q->where('id', '!=', $excludeNttnLinkId))
            ->get(['id', 'link_id', 'peering_vlan']);

        foreach ($nttnLinks as $link) {
            if (VlanRange::overlaps($vlan, $link->peering_vlan)) {
                throw ValidationException::withMessages([
                    $fieldName => "VLAN {$vlan} overlaps with the peering VLAN ({$link->peering_vlan}) of NTTN link \"{$link->link_id}\".",
                ]);
            }
        }
    }
}
