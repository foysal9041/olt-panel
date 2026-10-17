<?php

namespace App\Services;

use App\Models\SwitchPortTraffic;
use Illuminate\Support\Facades\DB;

/**
 * Device metrics (memory %, uptime, SNMP response time, sensors) in the
 * same 1-minute / 30-minute / 2-hour / 1-day buckets as port traffic:
 * sum, peak and sample count per bucket, one upsert per poll.
 */
class MetricStore
{
    /** How each metric is stored: value × scale as an integer. */
    public const SCALE = ['mem' => 100, 'uptime' => 1, 'snmp_ms' => 1, 'sensor' => 100];

    public static function scale(string $metric): int
    {
        return self::SCALE[explode(':', $metric)[0]] ?? 1;
    }

    /** @param  array<string, int|float>  $values  metric => real value */
    public function add(int $deviceId, array $values, ?int $at = null): void
    {
        if (! $values) {
            return;
        }
        $at ??= time();
        $rows = [];
        $bindings = [];
        foreach (array_keys(SwitchPortTraffic::RESOLUTIONS) as $res) {
            $bucket = SwitchPortTraffic::bucket($at, $res);
            foreach ($values as $metric => $value) {
                $v = (int) round($value * self::scale($metric));
                $rows[] = '(?, ?, ?, ?, ?, ?, 1)';
                array_push($bindings, $deviceId, $metric, $res, $bucket, $v, $v);
            }
        }

        DB::statement(
            'INSERT INTO device_metrics (network_switch_id, metric, res, at, value_sum, value_max, samples) VALUES ' . implode(', ', $rows)
            . ' ON DUPLICATE KEY UPDATE value_sum = value_sum + VALUES(value_sum), value_max = GREATEST(value_max, VALUES(value_max)), samples = samples + 1',
            $bindings
        );
    }

    public function prune(): int
    {
        $deleted = 0;
        foreach (SwitchPortTraffic::RESOLUTIONS as $res => $days) {
            do {
                $n = DB::table('device_metrics')->where('res', $res)->where('at', '<', time() - $days * 86400)->limit(5000)->delete();
                $deleted += $n;
            } while ($n > 0);
        }

        return $deleted;
    }
}
