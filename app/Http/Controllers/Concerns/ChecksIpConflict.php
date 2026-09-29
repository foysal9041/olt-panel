<?php

namespace App\Http\Controllers\Concerns;

use App\Services\IpInventory;
use Illuminate\Validation\ValidationException;

/**
 * A device's management IP must not already be given to another record —
 * another OLT or switch, a subnet's gateway or an NTTN peering IP (see
 * IpInventory, the one register of every IP in the panel).
 */
trait ChecksIpConflict
{
    private function assertIpNotUsed(?string $ip, string $source, ?int $id = null, string $fieldName = 'ip'): void
    {
        if (blank($ip)) {
            return;
        }

        $conflicts = (new IpInventory)->hostConflicts($ip, $source, $id);

        if ($conflicts) {
            $c = $conflicts[0];
            $what = IpInventory::SOURCES[$c['source']] . ' ' . IpInventory::ROLES[$c['role']];

            throw ValidationException::withMessages([
                $fieldName => "{$ip} is already used — {$what} of “{$c['label']}”. Check IP Management → All IP Records.",
            ]);
        }
    }
}
