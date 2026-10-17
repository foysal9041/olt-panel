<?php

namespace App\Services;

use App\Models\SwitchPort;
use App\Models\SwitchPortTraffic;
use App\Services\Snmp\SnmpClient;
use Illuminate\Support\Facades\DB;

/**
 * Port traffic for the Cacti-style graphs. Each poll reads the octet
 * counters (64-bit ifHC*, else the 32-bit IF-MIB ones), turns the change
 * since the last poll into bits per second, and adds that rate to the
 * port's 1-minute, 30-minute, 2-hour and 1-day buckets.
 */
class PortTrafficRecorder
{
    protected const HC_IN = '1.3.6.1.2.1.31.1.1.1.6';
    protected const HC_OUT = '1.3.6.1.2.1.31.1.1.1.10';
    protected const IN_OCTETS = '1.3.6.1.2.1.2.2.1.10';
    protected const OUT_OCTETS = '1.3.6.1.2.1.2.2.1.16';

    /** Packet, error and discard counters: name => [64-bit OID(s), 32-bit OID(s)] (several are added up). */
    protected const PACKET_OIDS = [
        'in_u' => [['1.3.6.1.2.1.31.1.1.1.7'], ['1.3.6.1.2.1.2.2.1.11']],
        'out_u' => [['1.3.6.1.2.1.31.1.1.1.11'], ['1.3.6.1.2.1.2.2.1.17']],
        'in_n' => [['1.3.6.1.2.1.31.1.1.1.8', '1.3.6.1.2.1.31.1.1.1.9'], ['1.3.6.1.2.1.2.2.1.12']],
        'out_n' => [['1.3.6.1.2.1.31.1.1.1.12', '1.3.6.1.2.1.31.1.1.1.13'], ['1.3.6.1.2.1.2.2.1.18']],
    ];

    /** Errors and discards are 32-bit counters everywhere. */
    protected const FAULT_OIDS = [
        'in_e' => '1.3.6.1.2.1.2.2.1.14',
        'out_e' => '1.3.6.1.2.1.2.2.1.20',
        'in_d' => '1.3.6.1.2.1.2.2.1.13',
        'out_d' => '1.3.6.1.2.1.2.2.1.19',
    ];

    /** A rate is only worked out over a gap this long (seconds); shorter keeps the old counters. */
    protected const MIN_GAP = 20;

    protected const MAX_GAP = 900;

    /** Above this share of the port speed the counters jumped — not real traffic. */
    protected const MAX_UTILISATION = 1.5;

    /** Ceiling when the port speed is unknown: 400 Gbps. */
    protected const MAX_BPS = 400_000_000_000;

    /**
     * Read the counters and set each port's rate (not saved — the poller
     * saves the ports). Returns the samples to store().
     *
     * @param  array<int, SwitchPort>  $ports  keyed by ifIndex
     * @return list<array{0: int, 1: int, 2: int, 3: ?array}> [port id, in bps, out bps, packet / error / discard rates]
     */
    public function measure(SnmpClient $snmp, array $ports): array
    {
        $packets = $this->packetCounters($snmp);
        $bits = 64;
        $in = $snmp->tryWalk(self::HC_IN);
        $out = $in ? $snmp->tryWalk(self::HC_OUT) : [];
        if (! $in || ! $out) {
            $bits = 32;
            $in = $snmp->tryWalk(self::IN_OCTETS);
            $out = $snmp->tryWalk(self::OUT_OCTETS);
        }
        $nowMs = (int) round(microtime(true) * 1000);
        $samples = [];

        foreach ($ports as $ifIndex => $port) {
            if (! isset($in[$ifIndex], $out[$ifIndex]) || ! is_numeric($in[$ifIndex]) || ! is_numeric($out[$ifIndex])) {
                continue;
            }
            $newIn = (int) $in[$ifIndex];
            $newOut = (int) $out[$ifIndex];

            $gap = $port->octets_at ? ($nowMs - $port->octets_at) / 1000 : null;
            if ($gap !== null && $gap < self::MIN_GAP && $port->counter_bits === $bits) {
                continue; // e.g. "Poll now" right after the scheduled poll — measure over the longer gap next time
            }

            $rate = $gap !== null && $gap <= self::MAX_GAP && $port->counter_bits === $bits && $port->in_octets !== null
                ? $this->rate($port, $bits, $newIn, $newOut, $gap)
                : null;

            $pkt = $this->packetRates($port, $packets, $ifIndex, $nowMs);

            $port->forceFill([
                'in_octets' => $newIn,
                'out_octets' => $newOut,
                'octets_at' => $nowMs,
                'counter_bits' => $bits,
                'in_bps' => $rate[0] ?? null,
                'out_bps' => $rate[1] ?? null,
                'traffic_at' => $rate ? now() : $port->traffic_at,
            ]);

            if ($rate) {
                $samples[] = [$port->id, $rate[0], $rate[1], $pkt];
            }
        }

        return $samples;
    }

    /**
     * Add one poll's rates to every resolution's bucket.
     *
     * @param  list<array{0: int, 1: int, 2: int}>  $samples
     */
    public function store(array $samples, ?int $at = null): void
    {
        if (! $samples) {
            return;
        }
        $at ??= time();

        $rows = [];
        $bindings = [];
        foreach (array_keys(SwitchPortTraffic::RESOLUTIONS) as $res) {
            $bucket = SwitchPortTraffic::bucket($at, $res);
            foreach ($samples as $sample) {
                [$portId, $inBps, $outBps] = $sample;
                $p = $sample[3] ?? null;
                $v = fn ($k) => (int) ($p[$k] ?? 0);
                $rows[] = '(?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                array_push($bindings, $portId, $res, $bucket, $inBps, $outBps, $inBps, $outBps,
                    $v('in_u'), $v('out_u'), $v('in_u'), $v('out_u'), $v('in_n'), $v('out_n'), $v('in_e'), $v('out_e'), $v('in_d'), $v('out_d'), $p ? 1 : 0);
            }
        }

        DB::statement(
            'INSERT INTO switch_port_traffic (switch_port_id, res, at, in_sum, out_sum, in_max, out_max, samples,'
            . ' pkt_in_sum, pkt_out_sum, pkt_in_max, pkt_out_max, npkt_in_sum, npkt_out_sum, err_in_sum, err_out_sum, disc_in_sum, disc_out_sum, pkt_samples) VALUES ' . implode(', ', $rows)
            . ' ON DUPLICATE KEY UPDATE in_sum = in_sum + VALUES(in_sum), out_sum = out_sum + VALUES(out_sum),'
            . ' in_max = GREATEST(in_max, VALUES(in_max)), out_max = GREATEST(out_max, VALUES(out_max)), samples = samples + 1,'
            . ' pkt_in_sum = pkt_in_sum + VALUES(pkt_in_sum), pkt_out_sum = pkt_out_sum + VALUES(pkt_out_sum),'
            . ' pkt_in_max = GREATEST(pkt_in_max, VALUES(pkt_in_max)), pkt_out_max = GREATEST(pkt_out_max, VALUES(pkt_out_max)),'
            . ' npkt_in_sum = npkt_in_sum + VALUES(npkt_in_sum), npkt_out_sum = npkt_out_sum + VALUES(npkt_out_sum),'
            . ' err_in_sum = err_in_sum + VALUES(err_in_sum), err_out_sum = err_out_sum + VALUES(err_out_sum),'
            . ' disc_in_sum = disc_in_sum + VALUES(disc_in_sum), disc_out_sum = disc_out_sum + VALUES(disc_out_sum),'
            . ' pkt_samples = pkt_samples + VALUES(pkt_samples)',
            $bindings
        );
    }

    /** Delete buckets older than each resolution keeps. */
    public function prune(): int
    {
        $deleted = 0;
        foreach (SwitchPortTraffic::RESOLUTIONS as $res => $days) {
            $cutoff = time() - $days * 86400;
            do {
                $n = SwitchPortTraffic::where('res', $res)->where('at', '<', $cutoff)->limit(5000)->delete();
                $deleted += $n;
            } while ($n > 0);
        }

        return $deleted;
    }

    /**
     * The device's packet / error / discard counters, one array per name
     * keyed by ifIndex (64-bit packet counters when it has them).
     *
     * @return array{bits: int, counters: array<string, array<int|string, int>>}
     */
    protected function packetCounters(SnmpClient $snmp): array
    {
        $walk = function (array $oids) use ($snmp) {
            $sum = null;
            foreach ($oids as $oid) {
                $rows = array_filter($snmp->tryWalk($oid), 'is_numeric');
                if ($rows) {
                    $sum ??= [];
                    foreach ($rows as $i => $v) {
                        $sum[$i] = ($sum[$i] ?? 0) + (int) $v;
                    }
                }
            }

            return $sum ?? [];
        };

        $first = $walk(self::PACKET_OIDS['in_u'][0]);
        $bits = $first ? 64 : 32;
        $counters = ['in_u' => $first ?: $walk(self::PACKET_OIDS['in_u'][1])];
        foreach (['out_u', 'in_n', 'out_n'] as $name) {
            $counters[$name] = $walk(self::PACKET_OIDS[$name][$bits === 64 ? 0 : 1]);
        }
        foreach (self::FAULT_OIDS as $name => $oid) {
            $counters[$name] = $walk([$oid]);
        }

        return ['bits' => $bits, 'counters' => $counters];
    }

    /**
     * This port's packets per second (unicast / non-unicast) and errors and
     * discards in thousandths per second since the last poll; the new
     * counters are kept on the port. Null when there is nothing to compare.
     *
     * @return array<string, int>|null
     */
    protected function packetRates(SwitchPort $port, array $packets, int $ifIndex, int $nowMs): ?array
    {
        $now = [];
        foreach ($packets['counters'] as $name => $rows) {
            if (isset($rows[$ifIndex])) {
                $now[$name] = $rows[$ifIndex];
            }
        }
        if (! $now) {
            return null;
        }

        $before = $port->pkt_counters ?: [];
        $gap = isset($before['at']) ? ($nowMs - $before['at']) / 1000 : null;
        $rates = null;

        if ($gap !== null && $gap >= self::MIN_GAP && $gap <= self::MAX_GAP && ($before['bits'] ?? null) === $packets['bits']) {
            $rates = [];
            foreach ($now as $name => $value) {
                if (! isset($before[$name])) {
                    continue;
                }
                $delta = $value - $before[$name];
                $is64 = $packets['bits'] === 64 && ! isset(self::FAULT_OIDS[$name]);
                if ($delta < 0) {
                    if ($is64) {
                        continue; // counters were cleared / device rebooted
                    }
                    $delta += 2 ** 32;
                }
                $rates[$name] = isset(self::FAULT_OIDS[$name]) ? (int) round($delta * 1000 / $gap) : (int) round($delta / $gap);
            }

            // Past what the link can carry (smallest frames): a counter jump, not traffic.
            $maxPps = $port->speed_mbps ? $port->speed_mbps * 1_000_000 / 512 * self::MAX_UTILISATION : 600_000_000;
            if (max($rates['in_u'] ?? 0, $rates['out_u'] ?? 0) > $maxPps) {
                $rates = null;
            }
        }

        $port->pkt_counters = $now + ['at' => $nowMs, 'bits' => $packets['bits']];
        $port->pkt_rates = $rates;

        return $rates;
    }

    /**
     * Bits per second in and out since the last poll, or null when the
     * counters went back (device rebooted / counters cleared) or jumped
     * past what the link can carry.
     *
     * @return array{0: int, 1: int}|null
     */
    protected function rate(SwitchPort $port, int $bits, int $newIn, int $newOut, float $gap): ?array
    {
        $deltas = [];
        foreach ([[$newIn, (int) $port->in_octets], [$newOut, (int) $port->out_octets]] as [$now, $before]) {
            $delta = $now - $before;
            if ($delta < 0) {
                if ($bits === 64) {
                    return null;
                }
                $delta += 2 ** 32; // 32-bit counter wrapped
            }
            $deltas[] = $delta;
        }

        $limit = $port->speed_mbps ? $port->speed_mbps * 1_000_000 * self::MAX_UTILISATION : self::MAX_BPS;
        $rate = [(int) round($deltas[0] * 8 / $gap), (int) round($deltas[1] * 8 / $gap)];

        return $rate[0] > $limit || $rate[1] > $limit ? null : $rate;
    }
}
