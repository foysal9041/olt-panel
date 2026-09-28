<?php

namespace App\Console\Commands;

use App\Models\NocAlertSetting;
use App\Models\NttnLink;
use App\Services\NttnMonitor;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;

class CheckNttnLinks extends Command
{
    protected $signature = 'app:check-nttn-links';

    protected $description = 'Ping monitored NTTN links and send Telegram alerts when one goes down or comes back';

    public function handle(NttnMonitor $monitor, TelegramNotifier $telegram)
    {
        $links = NttnLink::where('monitor', true)
            ->where('status', 'active')
            ->get()
            ->filter(fn (NttnLink $link) => $link->pingTarget() !== null);

        if ($links->isEmpty()) {
            return Command::SUCCESS;
        }

        $now = now();
        $results = $monitor->pingMany($links);
        $alerts = [];

        foreach ($links as $link) {
            $r = $results[$link->id];

            if ($block = $monitor->record($link, $r, $now)) {
                $alerts[] = $block;
            }

            $this->line(sprintf('%s (%s) => %s', $link->link_id, $r['target'], $r['received'] ? "up {$r['avg']} ms" : 'no reply'));
        }

        if ($alerts && NocAlertSetting::current()->alert_nttn_status) {
            $telegram->send(implode("\n\n", $alerts));
        }

        return Command::SUCCESS;
    }
}
