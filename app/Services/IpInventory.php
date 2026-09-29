<?php

namespace App\Services;

use App\Models\IpBlock;
use App\Models\IpPool;
use App\Models\NetworkSwitch;
use App\Models\NttnLink;
use App\Models\Olt;
use App\Models\ZkDevice;
use App\Support\SubnetRange;
use Illuminate\Support\Collection;

/**
 * Every IP address and subnet recorded anywhere in the panel — OLT and
 * switch management IPs, IP Management subnets (public, gateway, private
 * NAT range), NTTN link IPs and attendance devices — in one register, so
 * an IP can be looked up in one place and one given out twice shows up.
 *
 * "checked" rows are allocations and must be unique: the same host IP on
 * two records, or two overlapping subnets, is a duplicate. A host inside a
 * subnet is normal. Reference rows (NTTN ping targets, the address an
 * attendance device connects from) are listed but never counted.
 */
class IpInventory
{
    public const SOURCES = [
        'olt' => 'OLT',
        'switch' => 'Switch',
        'pool' => 'IP Subnet',
        'nttn' => 'NTTN Link',
        'device' => 'Attendance',
    ];

    public const ROLES = [
        'mgmt' => 'Management IP',
        'subnet' => 'Subnet',
        'gateway' => 'Gateway',
        'private' => 'Private / NAT range',
        'public' => 'Public subnet',
        'peering' => 'Peering IP',
        'ping' => 'Ping target',
        'seen' => 'Connects from',
    ];

    /**
     * OLTs and switches each sit on their own point-to-point subnet
     * (gateway .1 on the router, device .2), so a management IP takes its
     * whole /30 when working out what's free.
     */
    public const DEVICE_PREFIX = 30;

    /** @var Collection<int, array>|null */
    private ?Collection $entries = null;

    /**
     * One row per recorded IP or subnet:
     * [min, max, text, kind (host|subnet), role, checked, source, id, label, detail, zone, url].
     *
     * @return Collection<int, array>
     */
    public function entries(): Collection
    {
        if ($this->entries) {
            return $this->entries;
        }

        $rows = collect();
        $add = function (?string $value, string $role, bool $checked, string $source, int $id, ?string $label, ?string $detail, ?string $zone, ?string $url) use ($rows) {
            $value = trim((string) $value);
            [$min, $max] = SubnetRange::parse($value);

            if ($min === null) {
                return;
            }

            $kind = str_contains($value, '/') && $min !== $max ? 'subnet' : 'host';
            $text = $kind === 'subnet' ? (SubnetRange::normalize($value) ?? $value) : long2ip($min);
            $zone = $zone ?: null;

            $rows->push(compact('min', 'max', 'text', 'kind', 'role', 'checked', 'source', 'id', 'label', 'detail', 'zone', 'url'));
        };

        foreach (Olt::get(['id', 'name', 'ip', 'zone', 'vlan']) as $o) {
            $add($o->ip, 'mgmt', true, 'olt', $o->id, $o->name, $o->vlan ? "VLAN {$o->vlan}" : null, $o->zone, route('olt.show', $o));
        }

        foreach (NetworkSwitch::get(['id', 'name', 'ip', 'zone', 'vendor']) as $s) {
            $add($s->ip, 'mgmt', true, 'switch', $s->id, $s->name, $s->vendor ? ucfirst($s->vendor) : null, $s->zone, route('switches.show', $s));
        }

        foreach (IpPool::get(['id', 'subnet', 'gateway', 'private_subnet', 'device', 'purpose', 'zone', 'vlan']) as $p) {
            $name = $p->device ?: $p->subnet;
            $detail = collect([$p->purpose, $p->vlan ? "VLAN {$p->vlan}" : null])->filter()->implode(' · ');
            $url = route('ip-pools.edit', $p);
            $add($p->subnet, 'subnet', true, 'pool', $p->id, $name, $detail, $p->zone, $url);
            $add($p->gateway, 'gateway', true, 'pool', $p->id, $name, "Gateway of {$p->subnet}", $p->zone, $url);
            $add($p->private_subnet, 'private', true, 'pool', $p->id, $name, "NAT behind {$p->subnet}", $p->zone, $url);
        }

        foreach (NttnLink::get(['id', 'link_id', 'location', 'zone', 'public_ip_subnet', 'private_ip_subnet', 'peering_ip', 'ping_ip']) as $l) {
            $url = route('nttn-links.show', $l);
            $add($l->public_ip_subnet, 'public', true, 'nttn', $l->id, $l->link_id, $l->location, $l->zone, $url);
            $add($l->private_ip_subnet, 'private', true, 'nttn', $l->id, $l->link_id, $l->location, $l->zone, $url);
            $add($l->peering_ip, 'peering', true, 'nttn', $l->id, $l->link_id, $l->location, $l->zone, $url);
            $add($l->ping_ip, 'ping', false, 'nttn', $l->id, $l->link_id, $l->location, $l->zone, $url);
        }

        foreach (ZkDevice::get(['id', 'name', 'serial_number', 'ip_address', 'zone']) as $d) {
            $add($d->ip_address, 'seen', false, 'device', $d->id, $d->name ?: $d->serial_number, 'Attendance device', $d->zone, route('attendance.devices.edit', $d));
        }

        return $this->entries = $rows->sortBy([['min', 'asc'], ['max', 'desc']])->values();
    }

    /**
     * The same IP on two records, or two records' subnets overlapping.
     *
     * @return list<array{text: string, entries: list<array>}>
     */
    public function duplicates(): array
    {
        $checked = $this->entries()->where('checked', true)->values();
        $out = [];

        // Host IPs
        foreach ($checked->where('kind', 'host')->groupBy('min') as $group) {
            if ($group->unique(fn ($e) => $e['source'] . ':' . $e['id'])->count() > 1) {
                $out[] = ['text' => $group->first()['text'], 'entries' => $group->values()->all()];
            }
        }

        // Overlapping subnets
        $subnets = $checked->where('kind', 'subnet')->values();
        $seen = [];

        foreach ($subnets as $i => $a) {
            foreach ($subnets as $j => $b) {
                if ($j <= $i || ($a['source'] === $b['source'] && $a['id'] === $b['id'])) {
                    continue;
                }

                if ($a['min'] <= $b['max'] && $b['min'] <= $a['max']) {
                    $key = $a['min'] <= $b['min'] ? $a['text'] : $b['text'];
                    $seen[$key] ??= [];
                    $seen[$key][$a['source'] . $a['id'] . $a['role']] = $a;
                    $seen[$key][$b['source'] . $b['id'] . $b['role']] = $b;
                }
            }
        }

        foreach ($seen as $text => $entries) {
            $out[] = ['text' => $text, 'entries' => array_values($entries)];
        }

        usort($out, fn ($x, $y) => SubnetRange::parse($x['text'])[0] <=> SubnetRange::parse($y['text'])[0]);

        return $out;
    }

    /**
     * Everything that is, contains or sits inside $value (an IP, CIDR or
     * range), the IP block it belongs to, and for a range how much of it
     * is used / free.
     *
     * @return array{entries: list<array>, block: ?IpBlock, usage: ?array, min: int, max: int}|null
     */
    public function lookup(string $value): ?array
    {
        if (! $range = self::parseRange($value)) {
            return null;
        }

        [$min, $max] = $range;

        $entries = $this->entries()
            ->filter(fn ($e) => $e['min'] <= $max && $min <= $e['max'])
            ->values()
            ->all();

        $block = IpBlock::get()->first(function (IpBlock $b) use ($min, $max) {
            [$bMin, $bMax] = $b->range();

            return $min >= $bMin && $max <= $bMax;
        });

        $usage = $max > $min ? $this->usage($min, $max) : null;

        return compact('entries', 'block', 'usage', 'min', 'max');
    }

    /**
     * Records already using host IP $ip as an allocation (not ping targets),
     * leaving out one record, e.g. ['olt', 5] while editing OLT 5.
     *
     * @return list<array>
     */
    public function hostConflicts(string $ip, ?string $exceptSource = null, ?int $exceptId = null): array
    {
        $long = ip2long(trim($ip));

        if ($long === false) {
            return [];
        }

        return $this->entries()
            ->filter(fn ($e) => $e['checked'] && $e['kind'] === 'host' && $e['min'] === $long
                && ! ($e['source'] === $exceptSource && $e['id'] === $exceptId))
            ->values()
            ->all();
    }

    /**
     * "192.168.50.0/24", "192.168.50.7", "192.168.50.1-192.168.50.40" or
     * the short "192.168.50.1-40" as [min, max] (integers), else null.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function parseRange(string $value): ?array
    {
        $value = preg_replace('/\s+/', '', $value);

        if (preg_match('/^(\d+\.\d+\.\d+\.\d+)-(\d+(?:\.\d+\.\d+\.\d+)?)$/', $value, $m)) {
            $from = ip2long($m[1]);
            $to = str_contains($m[2], '.') ? ip2long($m[2]) : ip2long(preg_replace('/\d+$/', $m[2], $m[1]));

            return $from !== false && $to !== false ? [min($from, $to), max($from, $to)] : null;
        }

        [$min, $max] = SubnetRange::parse($value);

        return $min === null ? null : [$min, $max];
    }

    public static function formatRange(int $min, int $max): string
    {
        if ($min === $max) {
            return long2ip($min);
        }

        $cidrs = SubnetRange::rangeToCidrs($min, $max);

        return count($cidrs) === 1 ? $cidrs[0] : long2ip($min) . ' – ' . long2ip($max);
    }

    /**
     * Every recorded address range merged into sorted, non-overlapping
     * [min, max] pairs — what's taken, whoever holds it. A device's
     * management IP counts as its whole /30 (see DEVICE_PREFIX).
     *
     * @return list<array{0: int, 1: int}>
     */
    public function usedRanges(): array
    {
        $merged = [];
        $mask = (~0 << (32 - self::DEVICE_PREFIX)) & 0xFFFFFFFF;

        $ranges = $this->entries()->map(function ($e) use ($mask) {
            if ($e['role'] === 'mgmt') {
                $net = $e['min'] & $mask;
                $e['min'] = $net;
                $e['max'] = $net | (~$mask & 0xFFFFFFFF);
            }

            return $e;
        });

        foreach ($ranges->sortBy('min') as $e) {
            $last = array_key_last($merged);

            if ($last !== null && $e['min'] <= $merged[$last][1] + 1) {
                $merged[$last][1] = max($merged[$last][1], $e['max']);
            } else {
                $merged[] = [$e['min'], $e['max']];
            }
        }

        return $merged;
    }

    /**
     * Used and free addresses inside [min, max]: counts plus the free holes.
     *
     * @return array{size: int, used: int, free: int, holes: list<array{0: int, 1: int}>}
     */
    public function usage(int $min, int $max): array
    {
        $used = 0;
        $holes = [];
        $cursor = $min;

        foreach ($this->usedRanges() as [$a, $b]) {
            if ($b < $min || $a > $max) {
                continue;
            }

            [$a, $b] = [max($a, $min), min($b, $max)];

            if ($a > $cursor) {
                $holes[] = [$cursor, $a - 1];
            }

            $used += $b - $a + 1;
            $cursor = $b + 1;
        }

        if ($cursor <= $max) {
            $holes[] = [$cursor, $max];
        }

        $size = $max - $min + 1;

        return ['size' => $size, 'used' => $used, 'free' => $size - $used, 'holes' => $holes];
    }

    /**
     * Free blocks for $count × /$prefix between $from and $to, like the
     * VLAN finder:
     *  - next: straight after the last address used in the range, keeping
     *    the running sequence (e.g. the next /30 after the last OLT);
     *  - gaps: free holes inside the used part of the range.
     * Blocks are aligned to their size (a /30 starts on a multiple of 4).
     * Single IPs (/32) skip addresses ending in .0 and .255.
     *
     * @return array{next: ?list<array{0:int,1:int}>, gaps: list<array{blocks: list<array{0:int,1:int}>, hole: array{0:int,1:int}, free: array{0:int,1:int}}>, first_used: ?int, last_used: ?int}
     */
    public function findFree(int $prefix, int $count, int $from, int $to, int $maxGaps = 6): array
    {
        $size = 2 ** (32 - $prefix);
        $need = $size * $count;
        $alignUp = fn (int $v) => (int) (ceil($v / $size) * $size);

        // A run of $count blocks starting at or after $start, inside [$start, $end].
        $fit = function (int $start, int $end) use ($prefix, $size, $count, $need, $alignUp): ?array {
            for ($a = $alignUp($start); $a + $need - 1 <= $end; $a += $size) {
                $blocks = [];

                for ($i = 0, $b = $a; $i < $count && $b + $size - 1 <= $end; $i++, $b += $size) {
                    if ($prefix === 32 && in_array($b & 0xFF, [0, 255], true)) {
                        break;
                    }
                    $blocks[] = [$b, $b + $size - 1];
                }

                if (count($blocks) === $count) {
                    return $blocks;
                }
            }

            return null;
        };

        $inRange = array_values(array_filter($this->usedRanges(), fn ($r) => $r[1] >= $from && $r[0] <= $to));
        $firstUsed = $inRange ? max($inRange[0][0], $from) : null;
        $lastUsed = $inRange ? min(end($inRange)[1], $to) : null;

        $next = $fit($lastUsed === null ? $from : $lastUsed + 1, $to);

        $gaps = [];

        if ($firstUsed !== null) {
            foreach ($this->usage($firstUsed, $lastUsed)['holes'] as [$a, $b]) {
                if ($blocks = $fit($a, $b)) {
                    // The hole trimmed to whole blocks of this size.
                    $free = [$alignUp($a), (int) (floor(($b + 1) / $size) * $size) - 1];
                    $gaps[] = ['blocks' => $blocks, 'hole' => [$a, $b], 'free' => $free];

                    if (count($gaps) >= $maxGaps) {
                        break;
                    }
                }
            }
        }

        return ['next' => $next, 'gaps' => $gaps, 'first_used' => $firstUsed, 'last_used' => $lastUsed];
    }

    /**
     * /24s that have recorded IPs, plus the IP blocks, for the quick range
     * buttons: [cidr, used].
     *
     * @return list<array{cidr: string, used: int, block: bool}>
     */
    public function series(): array
    {
        $out = [];

        foreach (IpBlock::orderBy('cidr')->get() as $b) {
            [$min, $max] = $b->range();
            $out[$b->cidr] = ['cidr' => $b->cidr, 'used' => $this->usage($min, $max)['used'], 'block' => true];
        }

        foreach ($this->entries() as $e) {
            if ($e['max'] - $e['min'] >= 256) {
                continue;
            }

            $cidr = long2ip($e['min'] & 0xFFFFFF00) . '/24';

            if (! isset($out[$cidr])) {
                [$min, $max] = SubnetRange::parse($cidr);
                $out[$cidr] = ['cidr' => $cidr, 'used' => $this->usage($min, $max)['used'], 'block' => false];
            }
        }

        uasort($out, fn ($a, $b) => SubnetRange::parse($a['cidr'])[0] <=> SubnetRange::parse($b['cidr'])[0]);

        return array_values($out);
    }
}
