<?php

namespace App\Services;

use App\Models\IpPool;
use App\Models\NttnLink;
use App\Models\Olt;
use App\Models\Vlan;
use App\Models\Zone;
use App\Support\VlanRange;
use Illuminate\Support\Collection;

/**
 * Every VLAN recorded anywhere in the panel — OLT VLANs, VLAN Management
 * entries, NTTN peering VLANs and IP pool VLANs — in one list, so we can
 * spot the same VLAN given out twice and find free VLANs in sequence.
 *
 * VLANs are unique per network: the core network is one VLAN space, and a
 * POP with its own switch (Zone::own_vlans) is another — 101-108 may be
 * used at several POPs. OLTs and VLAN Management entries belong to their
 * zone's network when that zone has its own VLANs; IP pool and NTTN
 * VLANs live on the core routers, so they're always core ($network = null).
 */
class VlanInventory
{
    public const MIN = 1;
    public const MAX = 4094;

    public const SOURCES = [
        'vlan' => 'VLAN Management',
        'olt' => 'OLT',
        'pool' => 'IP Pool',
        'nttn' => 'NTTN Link',
    ];

    /** @var Collection<int, array>|null */
    private ?Collection $entries = null;

    /** @var array<string, string>|null lower-cased zone name => zone name */
    private ?array $ownZones = null;

    /**
     * Zones that have their own VLAN space.
     *
     * @return array<string, string>
     */
    public function ownZones(): array
    {
        return $this->ownZones ??= Zone::where('own_vlans', true)->orderBy('name')->pluck('name')
            ->mapWithKeys(fn ($name) => [mb_strtolower($name) => $name])
            ->all();
    }

    /**
     * The network a record in $zone belongs to: the zone (own VLANs) or
     * null for the core network.
     */
    public function networkFor(?string $zone): ?string
    {
        return $zone ? ($this->ownZones()[mb_strtolower(trim($zone))] ?? null) : null;
    }

    public static function networkLabel(?string $network): string
    {
        return $network ?? 'Core network';
    }

    /**
     * One row per recorded range:
     * [from, to, source, id, label, detail, url, reserved, network, zone].
     * "reserved" marks a VLAN Management entry held for later — an OLT, IP
     * pool or NTTN link may take those VLANs; that's what they're for.
     *
     * @return Collection<int, array>
     */
    public function entries(): Collection
    {
        if ($this->entries) {
            return $this->entries;
        }

        $rows = collect();
        $add = function (string $source, int $id, ?string $value, string $label, ?string $detail, ?string $url, ?string $zone, bool $reserved = false) use ($rows) {
            $network = in_array($source, ['olt', 'vlan'], true) ? $this->networkFor($zone) : null;
            $zone = $zone ?: null;

            foreach (VlanRange::parseList($value) as [$from, $to]) {
                $rows->push(compact('from', 'to', 'source', 'id', 'label', 'detail', 'url', 'reserved', 'network', 'zone'));
            }
        };

        foreach (Vlan::whereNotNull('vlan')->where('vlan', '!=', '')->get() as $v) {
            $add('vlan', $v->id, $v->vlan, $v->name, trim(($v->zone ? $v->zone . ' · ' : '') . ucfirst($v->status)), route('vlans.edit', $v), $v->zone, $v->status === 'reserved');
        }

        foreach (Olt::whereNotNull('vlan')->where('vlan', '!=', '')->get(['id', 'name', 'ip', 'vlan', 'zone']) as $olt) {
            $add('olt', $olt->id, $olt->vlan, $olt->name, trim($olt->ip . ($olt->zone ? ' · ' . $olt->zone : '')), route('olt.show', $olt), $olt->zone);
        }

        foreach (IpPool::whereNotNull('vlan')->where('vlan', '!=', '')->get(['id', 'ip_block_id', 'subnet', 'device', 'purpose', 'zone', 'vlan']) as $pool) {
            $add('pool', $pool->id, $pool->vlan, $pool->subnet, collect([$pool->device, $pool->purpose, $pool->zone])->filter()->implode(' · '), route('ip-pools.edit', $pool), $pool->zone);
        }

        foreach (NttnLink::whereNotNull('peering_vlan')->where('peering_vlan', '!=', '')->get(['id', 'link_id', 'peering_vlan', 'zone']) as $link) {
            $add('nttn', $link->id, $link->peering_vlan, $link->link_id, 'Peering VLAN', route('nttn-links.show', $link), $link->zone);
        }

        return $this->entries = $rows->sortBy([['from', 'asc'], ['to', 'asc']])->values();
    }

    /**
     * Networks that have VLANs recorded, core first.
     *
     * @return list<?string>
     */
    public function networks(): array
    {
        $used = $this->entries()->pluck('network')->unique()->filter()->sort()->values()->all();

        return array_merge([null], $used);
    }

    /**
     * VLAN id => the entries using it, in one network.
     *
     * @return array<int, array<int, array>>
     */
    public function usage(?string $network = null): array
    {
        $map = [];

        foreach ($this->entries() as $i => $entry) {
            if ($entry['network'] !== $network) {
                continue;
            }

            for ($v = max($entry['from'], self::MIN); $v <= min($entry['to'], self::MAX); $v++) {
                $map[$v][$i] = $entry;
            }
        }

        ksort($map);

        return $map;
    }

    /**
     * VLANs recorded more than once in the same network, with consecutive
     * VLANs that share the same records merged into one range. A
     * reservation taken by one OLT, pool or NTTN link is fine; two of
     * those, or two reservations, is not.
     *
     * @return list<array{from: int, to: int, network: ?string, entries: list<array>}>
     */
    public function duplicates(): array
    {
        $groups = [];

        foreach ($this->networks() as $network) {
            $current = null;

            foreach ($this->usage($network) as $vlan => $entries) {
                $reserved = count(array_filter($entries, fn ($e) => $e['reserved']));

                if (count($entries) - $reserved < 2 && $reserved < 2) {
                    $current = null;
                    continue;
                }

                $key = implode(',', array_keys($entries));

                if ($current !== null && $groups[$current]['key'] === $key && $groups[$current]['to'] === $vlan - 1) {
                    $groups[$current]['to'] = $vlan;
                    continue;
                }

                $groups[] = ['from' => $vlan, 'to' => $vlan, 'network' => $network, 'key' => $key, 'entries' => array_values($entries)];
                $current = array_key_last($groups);
            }
        }

        return array_map(fn ($g) => array_diff_key($g, ['key' => 1]), $groups);
    }

    /**
     * Who already uses any VLAN in $value ("2505-2508", "20, 30-33") in
     * $network. $except skips one record, e.g. ['olt' => 5] while editing
     * OLT 5; $skipReserved leaves out reservations (an OLT/pool/NTTN link
     * may take them). Each hit carries the overlapping VLANs as "overlap".
     *
     * @return list<array>
     */
    public function conflicts(string $value, array $except = [], bool $skipReserved = false, ?string $network = null): array
    {
        $found = [];

        foreach (VlanRange::parseList($value) as [$from, $to]) {
            foreach ($this->entries() as $i => $entry) {
                if ($entry['network'] !== $network
                    || ($except[$entry['source']] ?? null) === $entry['id']
                    || ($skipReserved && $entry['reserved'])) {
                    continue;
                }

                if ($entry['from'] <= $to && $entry['to'] >= $from) {
                    $found[$i] ??= $entry + ['overlap' => []];
                    $found[$i]['overlap'][] = VlanRange::format(max($from, $entry['from']), min($to, $entry['to']));
                }
            }
        }

        return array_values(array_map(function ($e) {
            $e['overlap'] = implode(', ', array_unique($e['overlap']));

            return $e;
        }, $found));
    }

    /**
     * For each VLAN Management entry: the OLTs, pools and NTTN links in the
     * same network using its VLANs (a reservation taken into use).
     *
     * @return array<int, list<array>>
     */
    public function takenReservations(): array
    {
        $out = [];
        $others = $this->entries()->where('source', '!=', 'vlan');

        foreach ($this->entries()->where('source', 'vlan') as $r) {
            foreach ($others as $e) {
                if ($e['network'] === $r['network'] && $e['from'] <= $r['to'] && $e['to'] >= $r['from']) {
                    $out[$r['id']][] = $e;
                }
            }
        }

        return $out;
    }

    /**
     * Free blocks of $count consecutive VLANs between $from and $to in
     * $network:
     *  - next: straight after the highest VLAN used in that range, keeping
     *    the running sequence;
     *  - gaps: free holes inside the used part of the range that fit $count.
     * With $align (5, 10 …) blocks start on a multiple of it: 2505, 2510.
     * Each gap is [first, last, holeFrom, holeTo] — the block to use and the
     * whole free hole it sits in.
     *
     * @return array{next: ?array{0:int,1:int}, gaps: list<array{0:int,1:int,2:int,3:int}>, first_used: ?int, last_used: ?int}
     */
    public function findFree(int $count, int $from, int $to, int $align = 1, int $maxGaps = 6, ?string $network = null): array
    {
        $from = max(self::MIN, $from);
        $to = min(self::MAX, $to);
        $align = max(1, $align);
        $used = $this->usage($network);
        $alignUp = fn (int $v) => (int) (ceil($v / $align) * $align);

        $inRange = array_values(array_filter(array_keys($used), fn ($v) => $v >= $from && $v <= $to));
        $firstUsed = $inRange[0] ?? null;
        $lastUsed = $inRange ? end($inRange) : null;

        $start = $alignUp($lastUsed === null ? $from : $lastUsed + 1);
        $next = $start + $count - 1 <= $to ? [$start, $start + $count - 1] : null;

        $isFree = function (int $a, int $b) use ($used) {
            for ($v = $a; $v <= $b; $v++) {
                if (isset($used[$v])) {
                    return false;
                }
            }

            return true;
        };

        $gaps = [];

        if ($firstUsed !== null) {
            for ($v = $alignUp($firstUsed); $v + $count - 1 < $lastUsed && count($gaps) < $maxGaps; $v += $align) {
                if (! $isFree($v, $v + $count - 1)) {
                    continue;
                }

                $holeFrom = $v;
                while ($holeFrom - 1 >= $from && ! isset($used[$holeFrom - 1])) {
                    $holeFrom--;
                }

                // One suggestion per hole: move past the rest of it.
                $holeTo = $v;
                while ($holeTo + 1 <= $lastUsed && ! isset($used[$holeTo + 1])) {
                    $holeTo++;
                }

                $gaps[] = [$v, $v + $count - 1, $holeFrom, $holeTo];
                $v = $alignUp($holeTo + 1) - $align;
            }
        }

        return ['next' => $next, 'gaps' => $gaps, 'first_used' => $firstUsed, 'last_used' => $lastUsed];
    }

    /**
     * Ranges that have VLANs in use in $network, by thousand (1–999,
     * 1000–1999, …), for the quick range buttons.
     *
     * @return list<array{from: int, to: int, used: int, first: int, last: int}>
     */
    public function series(?string $network = null): array
    {
        $out = [];

        foreach (array_keys($this->usage($network)) as $v) {
            $bucket = intdiv($v, 1000);
            $out[$bucket] ??= ['from' => max(self::MIN, $bucket * 1000), 'to' => min(self::MAX, $bucket * 1000 + 999), 'used' => 0, 'first' => $v, 'last' => $v];
            $out[$bucket]['used']++;
            $out[$bucket]['last'] = $v;
        }

        return array_values($out);
    }
}
