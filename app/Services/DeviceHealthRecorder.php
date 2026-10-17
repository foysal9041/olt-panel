<?php

namespace App\Services;

use App\Models\DeviceSensor;
use App\Models\NetworkSwitch;
use App\Services\Snmp\SnmpClient;

/**
 * A switch's or router's health each poll, as LibreNMS graphs it: memory
 * use, uptime, SNMP response time and hardware sensors (temperature, fans,
 * voltage, current, power). Which memory MIB answers is remembered
 * (mem_source); sensors are discovered once an hour and then read by GET.
 */
class DeviceHealthRecorder
{
    protected const ENT_NAME = '1.3.6.1.2.1.47.1.1.1.1.7';

    /** Memory source => [OID, how rows become one %]. "hr" is HOST-RESOURCES RAM. */
    protected const MEMORY = [
        'cisco' => ['1.3.6.1.4.1.9.9.305.1.1.2', 'avg'],           // CISCO-SYSTEM-EXT-MIB cseSysMemoryUtilization
        'huawei' => ['1.3.6.1.4.1.2011.5.25.31.1.1.1.1.7', 'busy'],  // hwEntityMemUsage
        'juniper' => ['1.3.6.1.4.1.2636.3.1.13.1.11', 'busy'],        // jnxOperatingBuffer
        'hr' => [null, 'hr'],
    ];

    /** ENTITY-SENSOR sensor types we graph: type => class. */
    protected const ENTITY_TYPES = [8 => 'temperature', 10 => 'fan', 3 => 'voltage', 4 => 'voltage', 5 => 'current', 6 => 'power'];

    /** MikroTik gauge units: unit => [class, multiplier]. */
    protected const MIKROTIK_UNITS = [1 => ['temperature', 1], 2 => ['fan', 1], 3 => ['voltage', 0.1], 4 => ['current', 0.1], 5 => ['power', 0.1]];

    protected const MAX_SENSORS = 40;

    public function __construct(private MetricStore $store)
    {
    }

    /** Read everything, set it on the device (the poller saves it) and add it to the graphs. */
    public function record(SnmpClient $snmp, NetworkSwitch $switch, ?int $snmpMs): void
    {
        $values = [];
        if ($snmpMs !== null) {
            $switch->snmp_ms = $snmpMs;
            $values['snmp_ms'] = $snmpMs;
        }
        if ($switch->uptime_seconds !== null) {
            $values['uptime'] = $switch->uptime_seconds;
        }
        if (($mem = $this->memory($snmp, $switch)) !== null) {
            $values['mem'] = $mem;
        }
        foreach ($this->sensors($snmp, $switch) as $sensor) {
            if ($sensor->value !== null) {
                $values[$sensor->metric()] = $sensor->value;
            }
        }

        $this->store->add($switch->id, $values);
    }

    /** Memory used, % (two decimals). */
    protected function memory(SnmpClient $snmp, NetworkSwitch $switch): ?float
    {
        if ($switch->mem_source === 'none' && now()->minute !== 0) {
            return null;
        }
        $known = $switch->mem_source && isset(self::MEMORY[$switch->mem_source]) ? [$switch->mem_source] : null;
        $order = $known ?? array_values(array_unique(array_filter([isset(self::MEMORY[$switch->vendor]) ? $switch->vendor : null, 'hr', 'cisco', 'huawei', 'juniper'])));

        foreach ($order as $source) {
            [$oid, $mode] = self::MEMORY[$source];
            $pct = null;
            $total = null;
            if ($mode === 'hr') {
                [$pct, $total] = $this->hrMemory($snmp);
            } else {
                $rows = array_map('intval', array_filter($snmp->tryWalk($oid), 'is_numeric'));
                if ($mode === 'busy') {
                    $rows = array_filter($rows, fn ($v) => $v > 0);
                }
                $pct = $rows ? array_sum($rows) / count($rows) : null;
            }
            if ($pct === null) {
                continue;
            }
            $pct = max(0, min(100, round($pct, 2)));
            $switch->mem_source = $source;
            $switch->mem_percent = (int) round($pct);
            $switch->mem_total_bytes = $total;

            return $pct;
        }

        $switch->mem_source = $known ? null : 'none';
        $switch->mem_percent = null;

        return null;
    }

    /** HOST-RESOURCES: the RAM rows of hrStorageTable — [% used, total bytes]. */
    protected function hrMemory(SnmpClient $snmp): array
    {
        $types = $snmp->tryWalk('1.3.6.1.2.1.25.2.3.1.2');
        $ram = array_keys(array_filter($types, fn ($t) => str_ends_with((string) $t, '1.3.6.1.2.1.25.2.1.2')));
        if (! $ram) {
            return [null, null];
        }
        $oids = [];
        foreach ($ram as $i) {
            array_push($oids, "1.3.6.1.2.1.25.2.3.1.4.$i", "1.3.6.1.2.1.25.2.3.1.5.$i", "1.3.6.1.2.1.25.2.3.1.6.$i");
        }
        $v = $snmp->get($oids);
        $size = $used = 0;
        $bytes = 0;
        foreach ($ram as $i) {
            $unit = (int) ($v["1.3.6.1.2.1.25.2.3.1.4.$i"] ?? 0);
            $s = (int) ($v["1.3.6.1.2.1.25.2.3.1.5.$i"] ?? 0);
            $size += $s;
            $used += (int) ($v["1.3.6.1.2.1.25.2.3.1.6.$i"] ?? 0);
            $bytes += $s * $unit;
        }

        return $size > 0 ? [$used / $size * 100, $bytes ?: null] : [null, null];
    }

    /**
     * The device's sensors with this poll's values — discovered again once
     * an hour (or when it has none recorded yet), read by GET in between.
     *
     * @return \Illuminate\Support\Collection<int, DeviceSensor>
     */
    protected function sensors(SnmpClient $snmp, NetworkSwitch $switch)
    {
        if (! $switch->sensors_at || $switch->sensors_at->lt(now()->subHour())) {
            $this->discover($snmp, $switch);
            $switch->sensors_at = now();
        }

        $sensors = DeviceSensor::where('network_switch_id', $switch->id)->get();
        foreach ($sensors->chunk(20) as $chunk) {
            try {
                $raw = $snmp->get($chunk->pluck('value_oid')->all());
            } catch (\Throwable) {
                continue;
            }
            foreach ($chunk as $sensor) {
                $v = $raw[$sensor->value_oid] ?? null;
                $sensor->value = is_numeric($v) ? round((float) $v * $sensor->multiplier, 3) : null;
                $sensor->value_at = $sensor->value !== null ? now() : $sensor->value_at;
                $sensor->save();
            }
        }

        return $sensors;
    }

    /** Find the sensors and keep the list in step (new ones added, gone ones removed). */
    protected function discover(SnmpClient $snmp, NetworkSwitch $switch): void
    {
        $found = array_slice(
            $this->mikrotikGauges($snmp)
            ?: $this->entitySensors($snmp, '1.3.6.1.4.1.9.9.91.1.1.1.1', 'cisco')
            ?: $this->entitySensors($snmp, '1.3.6.1.2.1.99.1.1.1', 'entity')
            ?: $this->huaweiTemperatures($snmp),
            0, self::MAX_SENSORS
        );

        $keep = [];
        foreach ($found as $s) {
            $sensor = DeviceSensor::updateOrCreate(
                ['network_switch_id' => $switch->id, 'source' => $s['source'], 'sensor_index' => $s['index']],
                ['class' => $s['class'], 'name' => mb_substr($s['name'], 0, 120), 'unit' => DeviceSensor::CLASSES[$s['class']][1], 'value_oid' => $s['oid'], 'multiplier' => $s['multiplier']]
            );
            $keep[] = $sensor->id;
        }
        DeviceSensor::where('network_switch_id', $switch->id)->whereNotIn('id', $keep)->delete();
    }

    /** MikroTik RouterOS 7 gauge table (voltage, temperatures, fans, PSU). */
    protected function mikrotikGauges(SnmpClient $snmp): array
    {
        $base = '1.3.6.1.4.1.14988.1.1.3.100.1';
        $names = $snmp->tryWalk("$base.2");
        if (! $names) {
            return [];
        }
        $units = $snmp->tryWalk("$base.4");
        $out = [];
        foreach ($names as $i => $name) {
            [$class, $mult] = self::MIKROTIK_UNITS[(int) ($units[$i] ?? 0)] ?? [null, 1];
            if ($class) {
                $out[] = ['source' => 'mikrotik', 'index' => (string) $i, 'class' => $class, 'name' => ucfirst(str_replace('-', ' ', (string) $name)), 'oid' => "$base.3.$i", 'multiplier' => $mult];
            }
        }

        return $out;
    }

    /**
     * ENTITY-SENSOR-MIB (standard, or Cisco's copy): chassis sensors only —
     * transceiver readings are already on the ports.
     */
    protected function entitySensors(SnmpClient $snmp, string $base, string $source): array
    {
        $types = $snmp->tryWalk("$base.1");
        $pick = [];
        foreach ($types as $i => $type) {
            if (isset(self::ENTITY_TYPES[(int) $type]) && (int) $i < 300000000) {
                $pick[(string) $i] = self::ENTITY_TYPES[(int) $type];
            }
        }
        if (! $pick) {
            return [];
        }

        $out = [];
        foreach (array_chunk(array_keys($pick), 15) as $chunk) {
            $oids = [];
            foreach ($chunk as $i) {
                array_push($oids, "$base.2.$i", "$base.3.$i", self::ENT_NAME . ".$i");
            }
            try {
                $v = $snmp->get($oids);
            } catch (\Throwable) {
                continue;
            }
            foreach ($chunk as $i) {
                $name = trim((string) ($v[self::ENT_NAME . ".$i"] ?? ''), " \"");
                if (preg_match('/lane|transceiver|xcvr|sfp/i', $name)) {
                    continue;
                }
                // Scale: 9 = units, each step either side is ×1000; precision = decimal places.
                $scale = (int) ($v["$base.2.$i"] ?? 9);
                $precision = (int) ($v["$base.3.$i"] ?? 0);
                $out[] = [
                    'source' => $source, 'index' => (string) $i, 'class' => $pick[$i], 'name' => $name ?: ucfirst($pick[$i]) . " $i",
                    'oid' => "$base.4.$i", 'multiplier' => (10 ** (($scale - 9) * 3)) / (10 ** $precision),
                ];
            }
        }

        return $out;
    }

    /** Huawei: the boards that report a temperature. */
    protected function huaweiTemperatures(SnmpClient $snmp): array
    {
        $base = '1.3.6.1.4.1.2011.5.25.31.1.1.1.1.11';
        $temps = array_filter($snmp->tryWalk($base), fn ($v) => is_numeric($v) && (int) $v > 0);
        if (! $temps) {
            return [];
        }
        $out = [];
        foreach (array_chunk(array_keys($temps), 20) as $chunk) {
            try {
                $names = $snmp->get(array_map(fn ($i) => self::ENT_NAME . ".$i", $chunk));
            } catch (\Throwable) {
                $names = [];
            }
            foreach ($chunk as $i) {
                $out[] = ['source' => 'huawei', 'index' => (string) $i, 'class' => 'temperature', 'name' => trim((string) ($names[self::ENT_NAME . ".$i"] ?? "Board $i"), " \""), 'oid' => "$base.$i", 'multiplier' => 1];
            }
        }

        return $out;
    }
}
