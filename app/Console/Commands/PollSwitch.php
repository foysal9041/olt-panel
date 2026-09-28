<?php

namespace App\Console\Commands;

use App\Models\NetworkSwitch;
use App\Services\SwitchPoller;
use Illuminate\Console\Command;

class PollSwitch extends Command
{
    protected $signature = 'app:poll-switch {switch : Switch id}';

    protected $description = 'Poll one switch over SNMP (ports, transceivers) and send alerts';

    public function handle(SwitchPoller $poller)
    {
        $switch = NetworkSwitch::find($this->argument('switch'));

        if (! $switch) {
            $this->error('Switch not found.');

            return Command::FAILURE;
        }

        $result = $poller->poll($switch);

        if (! $result['ok']) {
            $this->error("{$switch->name} ({$switch->ip}): {$result['error']}");

            return Command::FAILURE;
        }

        $this->info("{$switch->name} ({$switch->ip}): {$result['ports']} ports, {$result['transceivers']} transceivers, {$result['events']} events");

        return Command::SUCCESS;
    }
}
