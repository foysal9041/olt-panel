<?php

namespace App\Services\Snmp;

use App\Models\NetworkSwitch;

/**
 * Reads SFP/transceiver DOM values (Rx/Tx power, temperature, voltage,
 * bias) and returns them keyed by ifIndex:
 *
 *   [ifIndex => ['rx_power' => dBm, 'tx_power' => dBm, 'temperature' => °C,
 *                'voltage' => V, 'bias' => mA]]
 *
 * Each vendor keeps these in a different MIB, so readers are tried in a
 * per-vendor order and the first one that returns anything wins.
 */
class TransceiverReader
{
    // ENTITY-MIB
    protected const ENT_PHYSICAL_NAME = '1.3.6.1.2.1.47.1.1.1.1.7';
    protected const ENT_PHYSICAL_CONTAINED_IN = '1.3.6.1.2.1.47.1.1.1.1.4';
    protected const ENT_ALIAS_MAPPING = '1.3.6.1.2.1.47.1.3.2.1.2';

    // ENTITY-SENSOR-MIB (standard; Arista and many others)
    protected const ENTITY_SENSOR = '1.3.6.1.2.1.99.1.1.1';

    // CISCO-ENTITY-SENSOR-MIB
    protected const CISCO_SENSOR = '1.3.6.1.4.1.9.9.91.1.1.1.1';

    // CISCO-ENTITY-SENSOR-MIB entSensorThresholdEntry (.2 severity,
    // .3 relation, .4 value), indexed by entPhysicalIndex.thresholdIndex
    protected const CISCO_THRESHOLD = '1.3.6.1.4.1.9.9.91.1.2.1.1';

    // JUNIPER-DOM-MIB jnxDomCurrentEntry, indexed by ifIndex
    protected const JUNIPER_DOM = '1.3.6.1.4.1.2636.3.60.1.1.1.1';

    // HUAWEI-ENTITY-EXTENT-MIB hwOpticalModuleInfoEntry, indexed by entPhysicalIndex
    protected const HUAWEI_OPTICAL = '1.3.6.1.4.1.2011.5.25.31.1.1.3.1';

    // MIKROTIK-MIB mtxrOpticalEntry, indexed by ifIndex
    protected const MIKROTIK_OPTICAL = '1.3.6.1.4.1.14988.1.1.19.1.1';

    protected const ORDER = [
        'cisco'    => ['cisco', 'entity'],
        'arista'   => ['entity', 'cisco'],
        'juniper'  => ['juniper', 'entity'],
        'huawei'   => ['huawei', 'entity'],
        'mikrotik' => ['mikrotik'],
        'bdcom'    => ['entity', 'cisco'],
        'dcn'      => ['entity', 'cisco'],
        'generic'  => ['entity', 'cisco'],
    ];

    /** @var array<int, array{name:?string, descr:?string}> */
    protected array $ports = [];

    /**
     * @param  array<int, array{name:?string, descr:?string}>  $ports  keyed by ifIndex
     */
    public function read(SnmpClient $snmp, NetworkSwitch $switch, array $ports): array
    {
        $this->ports = $ports;

        $readers = self::ORDER[$switch->vendor] ?? self::ORDER['generic'];

        if ($switch->dom_rx_oid || $switch->dom_tx_oid || $switch->dom_temp_oid) {
            array_unshift($readers, 'custom');
        }

        foreach ($readers as $reader) {
            $result = $this->{'read' . ucfirst($reader)}($snmp, $switch);

            if ($result) {
                return $result;
            }
        }

        return [];
    }

    // ---- Vendor readers ---------------------------------------------------

    protected function readJuniper(SnmpClient $snmp): array
    {
        $out = [];

        $columns = [
            'rx_power'    => [5, 100],   // 0.01 dBm
            'bias'        => [6, 1000],  // 0.001 mA
            'tx_power'    => [7, 100],   // 0.01 dBm
            'temperature' => [8, 1],     // °C
        ];

        foreach ($columns as $field => [$col, $div]) {
            foreach ($snmp->tryWalk(self::JUNIPER_DOM . '.' . $col) as $ifIndex => $value) {
                if (is_numeric($value)) {
                    $out[(int) $ifIndex][$field] = $value / $div;
                }
            }
        }

        return $this->onlyKnownPorts($out);
    }

    protected function readMikrotik(SnmpClient $snmp): array
    {
        $out = [];

        $columns = [
            'temperature' => [6, 1],     // °C
            'voltage'     => [7, 1000],  // mV -> V
            'bias'        => [8, 1],     // mA
            'tx_power'    => [9, 1000],  // 0.001 dBm
            'rx_power'    => [10, 1000], // 0.001 dBm
        ];

        foreach ($columns as $field => [$col, $div]) {
            foreach ($snmp->tryWalk(self::MIKROTIK_OPTICAL . '.' . $col) as $ifIndex => $value) {
                if (is_numeric($value)) {
                    $out[(int) $ifIndex][$field] = $value / $div;
                }
            }
        }

        // RouterOS lists every SFP cage but reports all zeros for modules
        // without DOM (or DOM it can't read). A real module is never at
        // 0 V and 0 °C at once, so treat that as "no readings".
        foreach ($out as $ifIndex => $dom) {
            if (empty($dom['voltage']) && empty($dom['temperature'])) {
                unset($out[$ifIndex]);
            }
        }

        return $this->onlyKnownPorts($out);
    }

    protected function readHuawei(SnmpClient $snmp): array
    {
        $rx = $snmp->tryWalk(self::HUAWEI_OPTICAL . '.8');

        if (! $rx) {
            return [];
        }

        $tx = $snmp->tryWalk(self::HUAWEI_OPTICAL . '.9');
        $temp = $snmp->tryWalk(self::HUAWEI_OPTICAL . '.5');
        $volt = $snmp->tryWalk(self::HUAWEI_OPTICAL . '.6');
        $bias = $snmp->tryWalk(self::HUAWEI_OPTICAL . '.7');

        // Older VRP reports power in µW; newer (CE series) in 0.01 dBm.
        // µW is never negative, so any negative reading means dBm.
        $inDbm = false;
        foreach (array_merge($rx, $tx) as $v) {
            if (is_numeric($v) && $v < 0 && (int) $v !== -1) {
                $inDbm = true;
                break;
            }
        }

        $power = function ($v) use ($inDbm) {
            if (! is_numeric($v) || (int) $v === -1 || (int) $v === 2147483647) {
                return null;
            }

            return $inDbm ? $v / 100 : $this->uwToDbm((float) $v);
        };

        $valid = fn ($v) => is_numeric($v) && (int) $v !== -1 && (int) $v !== 2147483647;

        $map = $this->entityToIfIndex($snmp);
        $out = [];

        foreach ($rx as $entIndex => $value) {
            $ifIndex = $map[(int) $entIndex] ?? null;

            if ($ifIndex === null) {
                continue;
            }

            $out[$ifIndex] = array_filter([
                'rx_power'    => $power($value),
                'tx_power'    => $power($tx[$entIndex] ?? null),
                'temperature' => $valid($temp[$entIndex] ?? null) ? (float) $temp[$entIndex] : null,
                'voltage'     => $valid($volt[$entIndex] ?? null) ? $volt[$entIndex] / 1000 : null,
                'bias'        => $valid($bias[$entIndex] ?? null) ? $bias[$entIndex] / 1000 : null,
            ], fn ($v) => $v !== null);
        }

        return $this->onlyKnownPorts($out);
    }

    protected function readCisco(SnmpClient $snmp): array
    {
        return $this->readSensorTable($snmp, self::CISCO_SENSOR);
    }

    protected function readEntity(SnmpClient $snmp): array
    {
        return $this->readSensorTable($snmp, self::ENTITY_SENSOR);
    }

    /**
     * Per-switch OIDs typed in on the switch form (indexed by ifIndex).
     */
    protected function readCustom(SnmpClient $snmp, NetworkSwitch $switch): array
    {
        $out = [];
        $div = max(1, (int) $switch->dom_divisor);

        $columns = [
            'rx_power' => $switch->dom_rx_oid,
            'tx_power' => $switch->dom_tx_oid,
            'temperature' => $switch->dom_temp_oid,
        ];

        foreach ($columns as $field => $oid) {
            if (! $oid) {
                continue;
            }

            foreach ($snmp->tryWalk(ltrim($oid, '.')) as $index => $value) {
                // Some firmwares return strings like "-3.21 dBm".
                if (! preg_match('/-?\d+(\.\d+)?/', (string) $value, $m)) {
                    continue;
                }

                $v = (float) $m[0] / $div;

                if ($field !== 'temperature') {
                    $v = match ($switch->dom_power_unit) {
                        'mw' => $this->uwToDbm($v * 1000),
                        'uw' => $this->uwToDbm($v),
                        default => $v,
                    };

                    if ($v === null) {
                        continue;
                    }
                }

                // Index may be "ifIndex" or "ifIndex.lane"; use the first part.
                $out[(int) explode('.', (string) $index)[0]][$field] = $v;
            }
        }

        return $this->onlyKnownPorts($out);
    }

    // ---- Sensor tables (ENTITY-SENSOR-MIB / CISCO-ENTITY-SENSOR-MIB) -------

    /**
     * Both MIBs share the column layout: .1 type, .2 scale, .3 precision,
     * .4 value, indexed by entPhysicalIndex. Which port and which reading a
     * sensor belongs to comes from its entPhysicalName, e.g.
     * "Te1/1/1 Receive Power Sensor" or "DOM Rx Power for Ethernet49/1".
     */
    protected function readSensorTable(SnmpClient $snmp, string $base): array
    {
        $values = $snmp->tryWalk($base . '.4');

        if (! $values) {
            return [];
        }

        $types = $snmp->tryWalk($base . '.1');
        $scales = $snmp->tryWalk($base . '.2');
        $precisions = $snmp->tryWalk($base . '.3');
        $names = $snmp->tryWalk(self::ENT_PHYSICAL_NAME);
        $parents = null;
        $aliasMap = null;

        $out = [];

        // Power sensors whose name doesn't say Rx or Tx, e.g. NX-OS's two
        // "Ethernet1/1(dBm)" sensors: [ifIndex => [entIndex => dBm]]
        $unlabeledPower = [];
        // Every unlabeled dBm sensor per port, zero-valued ones included,
        // so a missing Rx doesn't make Tx look like the first sensor.
        $powerSlots = [];
        // ifIndex => entPhysicalIndex of that port's Rx power sensor.
        $rxSensors = [];

        // First pass: the transceiver sensors and the port each belongs to.
        $sensors = [];

        foreach ($values as $entIndex => $raw) {
            $name = $names[$entIndex] ?? '';
            $type = (int) ($types[$entIndex] ?? 0);

            // Prefer what the name says; fall back to the sensor's unit
            // (NX-OS names them just "Ethernet1/1(volt)", "(celsius)", ...).
            $field = $this->classifySensor($name) ?? $this->classifyByType($type);

            if (! $field || ! is_numeric($raw)) {
                continue;
            }

            $ifIndex = $this->matchPortByName($name);

            if ($ifIndex === null) {
                $parents ??= $snmp->tryWalk(self::ENT_PHYSICAL_CONTAINED_IN);
                $aliasMap ??= $this->entityToIfIndex($snmp);
                $ifIndex = $this->climbToPort((int) $entIndex, $parents, $aliasMap);
            }

            // Not tied to a port: chassis/PSU/fan sensor.
            if ($ifIndex !== null) {
                $sensors[$entIndex] = [$raw, $type, $field, $ifIndex];
            }
        }

        $wholeUnits = $this->reportsWholeUnits($sensors, $scales, $precisions);

        foreach ($sensors as $entIndex => [$raw, $type, $field, $ifIndex]) {
            $value = $this->sensorValue($raw, $type, $scales[$entIndex] ?? 9, $precisions[$entIndex] ?? 0, $wholeUnits);

            if ($field === 'power') {
                $powerSlots[$ifIndex][] = (int) $entIndex;
            } elseif ($field === 'rx_power') {
                $rxSensors[$ifIndex] = (int) $entIndex;
            }

            if (in_array($field, ['rx_power', 'tx_power', 'power'])) {
                // Cisco reports exactly 0 when there's no reading (no light,
                // laser off); a real 0.000 dBm is vanishingly unlikely.
                if ((float) $raw == 0.0) {
                    continue;
                }

                if ($type === 6) {           // watts -> dBm
                    $value = $this->uwToDbm($value * 1_000_000);
                } elseif ($type !== 14 && $type !== 1) {
                    continue;                // not a power reading after all
                }
            } elseif ($field === 'bias' && $type === 5) {
                $value *= 1000;              // A -> mA
            }

            if ($value === null) {
                continue;
            }

            if ($field === 'power') {
                $unlabeledPower[$ifIndex][(int) $entIndex] = round($value, 3);
            } else {
                $out[$ifIndex][$field] = round($value, 3);
            }
        }

        // Cisco numbers a transceiver's sensors in a fixed order with Rx
        // power before Tx power, so with two unlabeled dBm sensors the
        // lower index is Rx. Zero readings were dropped above, so use the
        // full slot list to tell which one is missing.
        foreach ($powerSlots as $ifIndex => $siblings) {
            sort($siblings);
            $rxSensors[$ifIndex] ??= $siblings[0];

            foreach ($unlabeledPower[$ifIndex] ?? [] as $entIndex => $dbm) {
                $position = array_search($entIndex, $siblings, true);
                $field = $position === 0 ? 'rx_power' : ($position === 1 ? 'tx_power' : null);

                if ($field && ! isset($out[$ifIndex][$field])) {
                    $out[$ifIndex][$field] = $dbm;
                }
            }
        }

        if ($base === self::CISCO_SENSOR && $rxSensors) {
            foreach ($this->readCiscoRxThresholds($snmp, $rxSensors, $types, $scales, $precisions, $wholeUnits) as $ifIndex => $limits) {
                $out[$ifIndex] = ($out[$ifIndex] ?? []) + $limits;
            }
        }

        return $out;
    }

    /**
     * Scale: 9 = units, each step is x1000 (yocto=1 ... yotta=17), then
     * divided by 10^precision.
     */
    protected function sensorValue($raw, int $type, $scale, $precision, bool $wholeUnits): float
    {
        // See reportsWholeUnits(): power, temperature and voltage are
        // already in dBm / °C / V there. Bias stays scaled (it comes out
        // in mA either way).
        if ($wholeUnits && in_array($type, [3, 4, 8, 14], true)) {
            return (float) $raw;
        }

        return $raw * (10 ** (((int) $scale - 9) * 3)) / (10 ** (int) $precision);
    }

    /**
     * Older NX-OS (7.0(3)I2 and around) says "milli" for every transceiver
     * sensor but reports whole dBm, °C and volts (-1, 31, 3), so taking the
     * scale at its word turns 31 °C into 0.031 and -1 dBm into -0.001.
     * A module's supply voltage (~3.3 V) and temperature (well above 1 °C)
     * give it away: if, scaled, they come out implausibly small while the
     * raw numbers look like volts and degrees, the device reports whole
     * units.
     *
     * @param  array<int|string, array{0:mixed, 1:int, 2:string, 3:int}>  $sensors
     */
    protected function reportsWholeUnits(array $sensors, array $scales, array $precisions): bool
    {
        $whole = 0;
        $scaled = 0;

        foreach ($sensors as $entIndex => [$raw, $type]) {
            $scale = (int) ($scales[$entIndex] ?? 9);
            if ($scale === 9 || (float) $raw == 0.0) {
                continue;
            }

            $value = $this->sensorValue($raw, $type, $scale, $precisions[$entIndex] ?? 0, false);

            if (in_array($type, [3, 4], true)) {
                if ($value < 0.5 && $raw >= 1 && $raw <= 6) {
                    $whole++;
                } elseif ($value >= 1 && $value <= 6) {
                    $scaled++;
                }
            } elseif ($type === 8) {
                if (abs($value) < 1 && $raw >= 5 && $raw <= 100) {
                    $whole++;
                } elseif ($value >= 5 && $value <= 100) {
                    $scaled++;
                }
            }
        }

        return $whole > $scaled;
    }

    /**
     * The transceiver's own Rx alarm/warning limits.
     *
     * IOS fills in severity (minor=10 warning, major/critical alarm) and
     * relation (lessThan/greaterThan...). NX-OS leaves both at 1 and just
     * lists four rows in SFF-8472 order: high alarm, high warning, low
     * alarm, low warning.
     *
     * @param  array<int, int>  $rxSensors  ifIndex => entPhysicalIndex
     * @return array<int, array<string, float>>
     */
    protected function readCiscoRxThresholds(SnmpClient $snmp, array $rxSensors, array $types, array $scales, array $precisions, bool $wholeUnits = false): array
    {
        $values = $snmp->tryWalk(self::CISCO_THRESHOLD . '.4');

        if (! $values) {
            return [];
        }

        $severities = $snmp->tryWalk(self::CISCO_THRESHOLD . '.2');
        $relations = $snmp->tryWalk(self::CISCO_THRESHOLD . '.3');

        $rows = [];
        foreach ($values as $key => $value) {
            [$entIndex, $n] = array_map('intval', explode('.', (string) $key) + [1 => 0]);
            $rows[$entIndex][$n] = [
                'severity' => (int) ($severities[$key] ?? 1),
                'relation' => (int) ($relations[$key] ?? 1),
                'value' => $value,
            ];
        }

        $out = [];

        foreach ($rxSensors as $ifIndex => $entIndex) {
            if (empty($rows[$entIndex])) {
                continue;
            }

            $scale = (int) ($scales[$entIndex] ?? 9);
            $precision = (int) ($precisions[$entIndex] ?? 0);
            $type = (int) ($types[$entIndex] ?? 14);

            $toDbm = function ($raw) use ($scale, $precision, $type, $wholeUnits) {
                if (! is_numeric($raw)) {
                    return null;
                }

                $v = $this->sensorValue($raw, $type, $scale, $precision, $wholeUnits);
                $v = $type === 6 ? $this->uwToDbm($v * 1_000_000) : round($v, 2);

                // Modules without a limit report sentinels like -2147483648;
                // no real optical threshold sits outside this range.
                return $v !== null && $v >= -60 && $v <= 30 ? $v : null;
            };

            $thresholds = $rows[$entIndex];
            ksort($thresholds);
            $limits = [];

            $informative = count(array_unique(array_column($thresholds, 'relation'))) > 1
                || count(array_unique(array_column($thresholds, 'severity'))) > 1;

            if ($informative) {
                foreach ($thresholds as $t) {
                    $side = in_array($t['relation'], [3, 4]) ? 'high' : (in_array($t['relation'], [1, 2]) ? 'low' : null);

                    if ($side) {
                        $level = $t['severity'] >= 20 ? 'alarm' : 'warn';
                        $limits["rx_{$side}_{$level}"] = $toDbm($t['value']);
                    }
                }
            }

            $limits = array_filter($limits, fn ($v) => $v !== null);

            // Older NX-OS fills in relations that contradict the values
            // (its "low" limits sit above the "high" ones) but still lists
            // the four rows in SFF-8472 order, so read them that way.
            if (count($thresholds) === 4 && (! $informative || $this->limitsInverted($limits))) {
                [$highAlarm, $highWarn, $lowAlarm, $lowWarn] = array_values($thresholds);

                $limits = array_filter([
                    'rx_high_alarm' => $toDbm($highAlarm['value']),
                    'rx_high_warn' => $toDbm($highWarn['value']),
                    'rx_low_alarm' => $toDbm($lowAlarm['value']),
                    'rx_low_warn' => $toDbm($lowWarn['value']),
                ], fn ($v) => $v !== null);
            }

            // Sanity check: the low limits must sit below the high ones,
            // and all-zero rows mean the module didn't report any.
            if (! $limits || $this->limitsInverted($limits)) {
                continue;
            }

            $out[$ifIndex] = $limits;
        }

        return $out;
    }

    /** True when a low Rx limit is not below the high one. */
    protected function limitsInverted(array $limits): bool
    {
        $low = $limits['rx_low_warn'] ?? $limits['rx_low_alarm'] ?? null;
        $high = $limits['rx_high_warn'] ?? $limits['rx_high_alarm'] ?? null;

        return $low !== null && $high !== null && $low >= $high;
    }

    /**
     * Sensor unit -> field, for sensors whose name gives no hint. Only used
     * once the sensor has been tied to a port, so chassis sensors of the
     * same unit are never picked up.
     */
    protected function classifyByType(int $type): ?string
    {
        return match ($type) {
            3, 4 => 'voltage',   // voltsAC / voltsDC
            5 => 'bias',         // amperes
            8 => 'temperature',  // celsius
            14 => 'power',       // dBm, Rx or Tx decided later
            default => null,
        };
    }

    protected function classifySensor(string $name): ?string
    {
        $n = strtolower($name);

        return match (true) {
            (bool) preg_match('/\brx\b.*power|rx ?power|receive power/', $n) => 'rx_power',
            (bool) preg_match('/\btx\b.*power|tx ?power|transmit power/', $n) => 'tx_power',
            str_contains($n, 'bias') => 'bias',
            str_contains($n, 'temp') && $this->looksLikePortSensor($n) => 'temperature',
            str_contains($n, 'volt') && $this->looksLikePortSensor($n) => 'voltage',
            default => null,
        };
    }

    /**
     * Chassis/PSU temperature and voltage sensors share keywords with
     * transceiver ones; only accept those that also name a port.
     */
    protected function looksLikePortSensor(string $lowerName): bool
    {
        return (bool) preg_match('/(transceiver|module|dom|sfp|ethernet|\d+\/\d+)/', $lowerName);
    }

    /**
     * Longest port name (ifName or ifDescr) that appears in the sensor name
     * as a whole token, so "Ethernet4" doesn't match "Ethernet49/1".
     */
    protected function matchPortByName(string $sensorName): ?int
    {
        $best = null;
        $bestLen = 0;

        foreach ($this->ports as $ifIndex => $port) {
            foreach ([$port['name'] ?? null, $port['descr'] ?? null] as $candidate) {
                if (! $candidate || strlen($candidate) <= $bestLen) {
                    continue;
                }

                $pattern = '/(?<![\w\/])' . preg_quote($candidate, '/') . '(?![\w\/.:])/i';

                if (preg_match($pattern, $sensorName)) {
                    $best = $ifIndex;
                    $bestLen = strlen($candidate);
                }
            }
        }

        return $best;
    }

    /**
     * Walk up entPhysicalContainedIn until we reach an entity that
     * ENTITY-MIB's alias mapping ties to an ifIndex.
     */
    protected function climbToPort(int $entIndex, array $parents, array $aliasMap): ?int
    {
        for ($i = 0; $i < 4 && $entIndex > 0; $i++) {
            if (isset($aliasMap[$entIndex])) {
                return $aliasMap[$entIndex];
            }

            $entIndex = (int) ($parents[$entIndex] ?? 0);
        }

        return null;
    }

    /**
     * entPhysicalIndex => ifIndex, from entAliasMappingIdentifier (values
     * look like ".1.3.6.1.2.1.2.2.1.1.12"), falling back to matching the
     * entity's name against port names.
     *
     * @return array<int, int>
     */
    protected function entityToIfIndex(SnmpClient $snmp): array
    {
        $map = [];

        foreach ($snmp->tryWalk(self::ENT_ALIAS_MAPPING) as $key => $value) {
            if (preg_match('/1\.3\.6\.1\.2\.1\.2\.2\.1\.1\.(\d+)$/', (string) $value, $m)) {
                $map[(int) explode('.', (string) $key)[0]] = (int) $m[1];
            }
        }

        if (! $map) {
            foreach ($snmp->tryWalk(self::ENT_PHYSICAL_NAME) as $entIndex => $name) {
                foreach ($this->ports as $ifIndex => $port) {
                    if ($name !== '' && (strcasecmp($name, (string) $port['name']) === 0 || strcasecmp($name, (string) $port['descr']) === 0)) {
                        $map[(int) $entIndex] = $ifIndex;
                        break;
                    }
                }
            }
        }

        return $map;
    }

    // ---- Helpers ------------------------------------------------------------

    protected function uwToDbm(float $microwatts): ?float
    {
        if ($microwatts < 0) {
            return null;
        }

        // No light at all: clamp instead of -INF.
        return $microwatts == 0 ? -40.0 : round(10 * log10($microwatts / 1000), 2);
    }

    protected function onlyKnownPorts(array $out): array
    {
        return array_intersect_key(array_filter($out), $this->ports);
    }
}
