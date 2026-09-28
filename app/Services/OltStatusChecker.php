<?php

namespace App\Services;

use App\Models\Olt;
use Illuminate\Process\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;

/**
 * Decides whether OLTs are reachable by pinging them in parallel.
 *
 * An OLT counts as up if any of its pings is answered, so a single dropped
 * packet doesn't flip it to DOWN. The IP is passed as its own argv entry
 * (no shell), so it can't be used to inject commands.
 */
class OltStatusChecker
{
    protected const PINGS = 3;

    /**
     * @param  Collection<int, Olt>  $olts
     * @return array<int, bool> reachable, keyed by OLT id
     */
    public function check(Collection $olts): array
    {
        $results = [];

        foreach ($olts->chunk(25) as $chunk) {
            $pool = Process::pool(function (Pool $pool) use ($chunk) {
                foreach ($chunk as $olt) {
                    $pool->as((string) $olt->id)
                        ->timeout(10)
                        ->command(['ping', '-n', '-c', (string) self::PINGS, '-i', '0.2', '-W', '1', '-w', '3', $olt->ip]);
                }
            })->start()->wait();

            foreach ($chunk as $olt) {
                $results[$olt->id] = $pool[(string) $olt->id]->successful();
            }
        }

        return $results;
    }

    /**
     * Check a single OLT and store the result. Returns the new status.
     */
    public function refresh(Olt $olt): int
    {
        $olt->status = $this->check(collect([$olt]))[$olt->id] ? 1 : 0;
        $olt->save();

        return $olt->status;
    }
}
