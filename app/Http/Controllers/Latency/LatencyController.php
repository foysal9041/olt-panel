<?php

namespace App\Http\Controllers\Latency;

use App\Http\Controllers\Controller;
use App\Models\LatencyTarget;
use App\Services\LatencyProber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LatencyController extends Controller
{
    public function index(Request $request)
    {
        $targets = LatencyTarget::where('is_active', true)
            ->when($request->query('group'), fn ($q, $group) => $q->where('group', $group))
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        $groups = LatencyTarget::whereNotNull('group')->distinct()->orderBy('group')->pluck('group');

        $counts = $targets->countBy(fn ($target) => $target->status);

        return view('latency.index', compact('targets', 'groups', 'counts'));
    }

    public function show(LatencyTarget $target)
    {
        $ranges = config('latency.ranges');

        return view('latency.show', compact('target', 'ranges'));
    }

    /**
     * Graph data for one target over one range, bucketed down to roughly
     * `points` columns. Each bucket merges every ping RTT that fell inside
     * it and resamples them back to `pings` values — the SmokePing "smoke".
     *
     * Response: { start, end, step, pings, points: [[ts, median, loss%, [smoke...]], ...] }
     */
    public function data(Request $request, LatencyTarget $target)
    {
        $ranges = config('latency.ranges');
        $range = $ranges[$request->query('range')] ?? $ranges['3h'];
        $points = min(800, max(30, (int) $request->query('points', 300)));

        $step = max(60, (int) ceil($range['seconds'] / $points / 60) * 60);
        $end = now()->getTimestamp();
        $start = intdiv($end - $range['seconds'], $step) * $step;

        $rows = DB::table('latency_probes')
            ->where('latency_target_id', $target->id)
            ->where('probed_at', '>=', date('Y-m-d H:i:s', $start))
            ->orderBy('probed_at')
            ->select('probed_at', 'sent', 'received', 'median', 'rtts')
            ->cursor();

        $buckets = [];

        foreach ($rows as $row) {
            $i = intdiv(strtotime($row->probed_at) - $start, $step);
            $bucket = &$buckets[$i];
            $bucket ??= ['sent' => 0, 'received' => 0, 'medians' => [], 'rtts' => []];

            $bucket['sent'] += $row->sent;
            $bucket['received'] += $row->received;

            if ($row->median !== null) {
                $bucket['medians'][] = (float) $row->median;
            }

            if ($row->rtts) {
                array_push($bucket['rtts'], ...json_decode($row->rtts, true));
            }

            unset($bucket);
        }

        $pings = $target->pings;
        $out = [];

        foreach ($buckets as $i => $bucket) {
            sort($bucket['medians']);
            sort($bucket['rtts']);

            $out[] = [
                $start + $i * $step,
                ($median = LatencyProber::median($bucket['medians'])) === null ? null : round($median, 3),
                round(($bucket['sent'] - $bucket['received']) / max(1, $bucket['sent']) * 100, 2),
                $this->resample($bucket['rtts'], $pings),
            ];
        }

        return response()->json([
            'start' => $start,
            'end' => $end,
            'step' => $step,
            'pings' => $pings,
            'threshold' => $target->latency_threshold,
            'loss_threshold' => $target->loss_threshold,
            'points' => $out,
        ]);
    }

    /**
     * Pick `n` evenly spaced values (by rank) out of a sorted list so a
     * bucket holding many probes draws the same amount of smoke as one.
     *
     * @param  list<float>  $sorted
     * @return list<float>
     */
    protected function resample(array $sorted, int $n): array
    {
        $count = count($sorted);

        if ($count <= $n) {
            return $sorted;
        }

        $out = [];

        for ($i = 0; $i < $n; $i++) {
            $out[] = $sorted[(int) round($i * ($count - 1) / ($n - 1))];
        }

        return $out;
    }
}
