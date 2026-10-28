<?php

namespace App\Services;

use App\Models\SwitchEvent;
use App\Models\SwitchPortReading;
use App\Support\Retention;
use Illuminate\Support\Facades\DB;

/**
 * Deletes recorded history past what Settings → Data Retention keeps:
 * graph buckets, latency checks, optical readings and port events.
 * Runs daily (app:prune-data) and from the settings page's "Delete now".
 */
class DataPruner
{
    /** Tables per kind, for the settings page's sizes. */
    public const TABLES = [
        'graph' => ['switch_port_traffic', 'device_cpu', 'device_metrics'],
        'latency' => ['latency_probes'],
        'port_readings' => ['switch_port_readings'],
        'port_events' => ['switch_events'],
    ];

    /** @return array<string, int> rows deleted per kind */
    public function run(): array
    {
        @set_time_limit(0);
        $deleted = [
            'graph' => app(PortTrafficRecorder::class)->prune() + app(DeviceCpuRecorder::class)->prune() + app(MetricStore::class)->prune(),
            'latency' => $this->chunked(fn () => DB::table('latency_probes')->where('probed_at', '<', now()->subDays(Retention::days('latency')))->limit(5000)->delete()),
            'port_readings' => $this->chunked(fn () => SwitchPortReading::where('recorded_at', '<', now()->subDays(Retention::days('port_readings')))->limit(5000)->delete()),
            'port_events' => $this->chunked(fn () => SwitchEvent::where('occurred_at', '<', now()->subDays(Retention::days('port_events')))->limit(5000)->delete()),
        ];

        return $deleted;
    }

    /** In chunks, so a big backlog doesn't lock the table for long. */
    protected function chunked(callable $deleteSome): int
    {
        $total = 0;
        do {
            $n = $deleteSome();
            $total += $n;
        } while ($n > 0);

        return $total;
    }

    /**
     * Size and oldest record of each kind's tables.
     *
     * @return array<string, array{mb: float, rows: int, oldest: ?\Illuminate\Support\Carbon}>
     */
    public function sizes(): array
    {
        $info = collect(DB::select('SELECT table_name AS t, (data_length + index_length) AS b, table_rows AS r FROM information_schema.tables WHERE table_schema = DATABASE()'))
            ->keyBy(fn ($row) => $row->t);
        $oldest = [
            'graph' => ($at = DB::table('switch_port_traffic')->min('at')) ? \Illuminate\Support\Carbon::createFromTimestamp($at) : null,
            'latency' => ($d = DB::table('latency_probes')->min('probed_at')) ? \Illuminate\Support\Carbon::parse($d) : null,
            'port_readings' => ($d = DB::table('switch_port_readings')->min('recorded_at')) ? \Illuminate\Support\Carbon::parse($d) : null,
            'port_events' => ($d = DB::table('switch_events')->min('occurred_at')) ? \Illuminate\Support\Carbon::parse($d) : null,
        ];
        $out = [];
        foreach (self::TABLES as $kind => $tables) {
            $out[$kind] = [
                'mb' => round(collect($tables)->sum(fn ($t) => (int) ($info[$t]->b ?? 0)) / 1048576, 1),
                'rows' => (int) collect($tables)->sum(fn ($t) => (int) ($info[$t]->r ?? 0)),
                'oldest' => $oldest[$kind],
            ];
        }

        return $out;
    }
}
