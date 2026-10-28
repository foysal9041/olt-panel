<?php

namespace App\Services;

use App\Models\NetworkSwitch;
use App\Models\SwitchPortTraffic;
use App\Services\Snmp\SnmpClient;
use Illuminate\Support\Facades\DB;

/**
 * CPU load of a switch or router over SNMP, kept like port traffic (1-minute,
 * 30-minute, 2-hour and 1-day buckets). Which MIB answers is remembered on
 * the device (cpu_source), so each poll asks just that one.
 */
class DeviceCpuRecorder
{
    /** Source => [OID walked, how the rows become one number]. */
    protected const SOURCES = [
        'cisco' => ['1.3.6.1.4.1.9.9.109.1.1.1.1.7', 'avg'],          // CISCO-PROCESS-MIB cpmCPUTotal1minRev
        'huawei' => ['1.3.6.1.4.1.2011.5.25.31.1.1.1.1.5', 'busy'],  // HUAWEI-ENTITY-EXTENT-MIB hwEntityCpuUsage (boards without a CPU say 0)
        'juniper' => ['1.3.6.1.4.1.2636.3.1.13.1.8', 'busy'],         // JUNIPER-MIB jnxOperatingCPU
        'hr' => ['1.3.6.1.2.1.25.3.3.1.2', 'avg'],                    // HOST-RESOURCES-MIB hrProcessorLoad (MikroTik, Linux, Arista …)
    ];

    /**
     * Read the CPU % and set it on the device (not saved — the poller
     * saves the device). Null when the device doesn't report one.
     */
    public function measure(SnmpClient $snmp, NetworkSwitch $switch): ?int
    {
        // Nothing answered before: look again once an hour.
        if ($switch->cpu_source === 'none' && now()->minute !== 0) {
            return null;
        }

        $known = $switch->cpu_source && isset(self::SOURCES[$switch->cpu_source]) ? [$switch->cpu_source] : null;
        $order = $known ?? array_values(array_unique(array_filter([isset(self::SOURCES[$switch->vendor]) ? $switch->vendor : null, 'hr', 'cisco', 'huawei', 'juniper'])));

        foreach ($order as $source) {
            [$oid, $mode] = self::SOURCES[$source];
            $values = array_map('intval', array_filter($snmp->tryWalk($oid), 'is_numeric'));
            if (! $values) {
                continue;
            }
            if ($mode === 'busy') {
                $values = array_filter($values, fn ($v) => $v > 0) ?: [0];
            }
            $cpu = max(0, min(100, (int) round(array_sum($values) / count($values))));

            $switch->cpu_source = $source;
            $switch->cpu_percent = $cpu;
            $switch->cpu_at = now();

            return $cpu;
        }

        // The remembered MIB stopped answering: try them all next poll. Nothing at all: "none".
        $switch->cpu_source = $known ? null : 'none';
        $switch->cpu_percent = null;

        return null;
    }

    public function store(NetworkSwitch $switch, int $cpu, ?int $at = null): void
    {
        $at ??= time();
        $rows = [];
        $bindings = [];
        foreach (array_keys(SwitchPortTraffic::RESOLUTIONS) as $res) {
            $rows[] = '(?, ?, ?, ?, ?, 1)';
            array_push($bindings, $switch->id, $res, SwitchPortTraffic::bucket($at, $res), $cpu, $cpu);
        }

        DB::statement(
            'INSERT INTO device_cpu (network_switch_id, res, at, cpu_sum, cpu_max, samples) VALUES ' . implode(', ', $rows)
            . ' ON DUPLICATE KEY UPDATE cpu_sum = cpu_sum + VALUES(cpu_sum), cpu_max = GREATEST(cpu_max, VALUES(cpu_max)), samples = samples + 1',
            $bindings
        );
    }

    public function prune(): int
    {
        $deleted = 0;
        foreach (SwitchPortTraffic::resolutions() as $res => $days) {
            do {
                $n = DB::table('device_cpu')->where('res', $res)->where('at', '<', time() - $days * 86400)->limit(5000)->delete();
                $deleted += $n;
            } while ($n > 0);
        }

        return $deleted;
    }
}
