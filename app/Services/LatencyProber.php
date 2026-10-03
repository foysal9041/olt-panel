<?php

namespace App\Services;

use App\Models\LatencyTarget;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;

/**
 * Pings latency targets in parallel with the system `ping` binary and
 * returns every reply's RTT — the raw material for SmokePing-style graphs.
 */
class LatencyProber
{
    /**
     * @param  Collection<int, LatencyTarget>  $targets
     * @return array<int, array{sent:int, received:int, rtts:list<float>}> keyed by target id
     */
    public function probe(Collection $targets): array
    {
        $results = [];

        foreach ($targets->chunk(max(1, config('latency.concurrency'))) as $chunk) {
            try {
                $pool = Process::pool(function (Pool $pool) use ($chunk) {
                    foreach ($chunk as $target) {
                        $pool->as((string) $target->id)
                            ->timeout($this->deadline($target->pings) + 5)
                            ->command($this->command($target));
                    }
                })->start()->wait();
            } catch (ProcessTimedOutException $e) {
                report($e); // too busy this round — no sample rather than a fake loss

                continue;
            }

            foreach ($chunk as $target) {
                $rtts = $this->parse($pool[(string) $target->id]->output(), $target->pings);

                $results[$target->id] = [
                    'sent' => $target->pings,
                    'received' => count($rtts),
                    'rtts' => $rtts,
                ];
            }
        }

        return $results;
    }

    /**
     * Passed as an argv array (no shell), and hosts are validated on save,
     * so a target's host can't inject extra arguments or commands.
     */
    protected function command(LatencyTarget $target): array
    {
        return [
            'ping', '-n',
            '-c', (string) $target->pings,
            '-i', (string) config('latency.ping_interval'),
            '-W', '1',
            '-w', (string) $this->deadline($target->pings),
            $target->host,
        ];
    }

    protected function deadline(int $pings): int
    {
        return (int) ceil($pings * config('latency.ping_interval')) + 2;
    }

    /**
     * Pull "icmp_seq=N ... time=X ms" out of ping's output. Duplicate
     * replies (DUP!) are counted once so loss can't go negative.
     *
     * @return list<float> sorted ascending
     */
    public function parse(string $output, int $sent): array
    {
        preg_match_all('/icmp_seq=(\d+).*?time[=<]\s*([\d.]+)\s*ms/', $output, $matches, PREG_SET_ORDER);

        $bySeq = [];

        foreach ($matches as $match) {
            $bySeq[$match[1]] ??= round((float) $match[2], 3);
        }

        $rtts = array_slice(array_values($bySeq), 0, $sent);
        sort($rtts);

        return $rtts;
    }

    /**
     * @param  list<float>  $sorted
     */
    public static function median(array $sorted): ?float
    {
        $n = count($sorted);

        if ($n === 0) {
            return null;
        }

        $mid = intdiv($n, 2);

        return $n % 2 ? $sorted[$mid] : ($sorted[$mid - 1] + $sorted[$mid]) / 2;
    }
}
