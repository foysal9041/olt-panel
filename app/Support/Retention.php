<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * How long recorded history is kept before it is deleted automatically
 * (app:prune-data, daily). Set under Settings → Data Retention
 * (app_settings "retention"); the defaults are the panel's original ones.
 */
class Retention
{
    /** key => [label, help, default days, min, max] */
    public const KINDS = [
        'graph_1m' => ['Graphs — every minute', 'Traffic, CPU, memory and sensor readings per minute: the 1 hour … 48 hours graphs.', 3, 2, 14],
        'graph_30m' => ['Graphs — 30-minute averages', 'Weekly graphs and custom ranges.', 45, 8, 365],
        'graph_2h' => ['Graphs — 2-hour averages', 'Monthly graphs.', 400, 32, 1100],
        'graph_1d' => ['Graphs — daily averages', 'Yearly graphs.', 1100, 366, 3650],
        'latency' => ['Latency checks', 'Every ping of the latency targets.', 30, 1, 365],
        'port_readings' => ['Optical Rx/Tx readings', 'Transceiver power and temperature history of switch ports.', 90, 7, 730],
        'port_events' => ['Port events', 'Switch port up / down history.', 90, 7, 730],
    ];

    /** Graph bucket minutes => retention key. */
    public const GRAPH_BUCKETS = [1 => 'graph_1m', 30 => 'graph_30m', 120 => 'graph_2h', 1440 => 'graph_1d'];

    protected static ?array $settings = null;

    public static function all(): array
    {
        if (static::$settings === null) {
            $defaults = ['enabled' => true] + array_map(fn ($k) => $k[2], self::KINDS);
            try {
                static::$settings = AppSetting::get('retention', $defaults);
            } catch (\Throwable) {
                static::$settings = $defaults;
            }
        }

        return static::$settings;
    }

    public static function flush(): void
    {
        static::$settings = null;
    }

    /** Auto delete switched on. */
    public static function enabled(): bool
    {
        return (bool) (static::all()['enabled'] ?? true);
    }

    /** Days kept for one kind, always within its allowed range. */
    public static function days(string $kind): int
    {
        [, , $default, $min, $max] = self::KINDS[$kind];

        return max($min, min($max, (int) (static::all()[$kind] ?? $default)));
    }

    /** Graph bucket minutes => days kept (what SwitchPortTraffic::RESOLUTIONS used to be). */
    public static function graphResolutions(): array
    {
        return array_map(fn ($kind) => static::days($kind), self::GRAPH_BUCKETS);
    }
}
