<?php

namespace App\Console\Commands;

use App\Services\DataPruner;
use App\Support\Retention;
use Illuminate\Console\Command;

class PruneData extends Command
{
    protected $signature = 'app:prune-data {--force : Delete even while auto delete is switched off}';

    protected $description = 'Delete graph, latency, optical-reading and port-event history older than Settings → Data Retention keeps';

    public function handle(DataPruner $pruner)
    {
        if (! Retention::enabled() && ! $this->option('force')) {
            $this->info('Auto delete is switched off (Settings → Data Retention).');

            return Command::SUCCESS;
        }
        foreach ($pruner->run() as $kind => $n) {
            $this->line(str_pad($kind, 14) . number_format($n) . ' rows deleted');
        }

        return Command::SUCCESS;
    }
}
