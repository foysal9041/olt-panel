<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Olt;
use App\Services\OltStatusChecker;

class CheckOltStatus extends Command
{
    protected $signature = 'app:check-olt-status';

    protected $description = 'Check OLT status by ping and update database';

    public function handle(OltStatusChecker $checker)
    {
        $olts = Olt::all();

        $results = $checker->check($olts);

        foreach ($olts as $olt) {

            $olt->status = $results[$olt->id] ? 1 : 0;

            // Only writes when the status actually changed.
            $olt->save();

            $this->info(
                "{$olt->name} ({$olt->ip}) => {$olt->status}"
            );
        }

        return Command::SUCCESS;
    }
}
