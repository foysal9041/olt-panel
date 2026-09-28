<?php

namespace App\Console\Commands;

use App\Models\LatencyProbe;
use Illuminate\Console\Command;

class PruneLatency extends Command
{
    protected $signature = 'app:prune-latency';

    protected $description = 'Delete latency probe results older than the retention period';

    public function handle()
    {
        $cutoff = now()->subDays(config('latency.retention_days'));
        $deleted = 0;

        // Chunked so a big backlog doesn't lock the table for long.
        do {
            $batch = LatencyProbe::where('probed_at', '<', $cutoff)->limit(5000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Deleted {$deleted} latency probe rows older than {$cutoff}.");

        return Command::SUCCESS;
    }
}
