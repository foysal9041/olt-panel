<?php

namespace App\Console\Commands;

use App\Models\NetworkSwitch;
use Illuminate\Console\Command;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

class PollSwitches extends Command
{
    protected $signature = 'app:poll-switches';

    protected $description = 'Poll every active switch over SNMP, several at a time';

    /** Switches polled in parallel, each in its own process. */
    protected const CONCURRENCY = 10;

    public function handle()
    {
        $ids = NetworkSwitch::where('is_active', true)->pluck('id');

        foreach ($ids->chunk(self::CONCURRENCY) as $chunk) {
            $results = Process::pool(function (Pool $pool) use ($chunk) {
                foreach ($chunk as $id) {
                    $pool->as((string) $id)
                        ->path(base_path())
                        ->timeout(55)
                        ->command([PHP_BINARY, 'artisan', 'app:poll-switch', (string) $id]);
                }
            })->start()->wait();

            foreach ($results->collect() as $result) {
                $this->line(trim($result->output() . $result->errorOutput()));
            }
        }

        return Command::SUCCESS;
    }
}
