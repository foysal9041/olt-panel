<?php

namespace App\Console\Commands;

use App\Models\LatencyProbe;
use App\Models\LatencyTarget;
use App\Services\LatencyProber;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;

class ProbeLatency extends Command
{
    protected $signature = 'app:probe-latency {--target= : Only probe this target id}';

    protected $description = 'Ping every active latency target and record the results';

    public function handle(LatencyProber $prober, TelegramNotifier $telegram)
    {
        $probedAt = now()->startOfMinute();

        $targets = LatencyTarget::query()
            ->when($this->option('target'), fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->where('is_active', true))
            ->get();

        if ($targets->isEmpty()) {
            return Command::SUCCESS;
        }

        $results = $prober->probe($targets);
        $alerts = [];

        foreach ($targets as $target) {
            $result = $results[$target->id];
            $rtts = $result['rtts'];
            $median = LatencyProber::median($rtts);
            $loss = round(($result['sent'] - $result['received']) / $result['sent'] * 100, 2);

            LatencyProbe::create([
                'latency_target_id' => $target->id,
                'probed_at' => $probedAt,
                'sent' => $result['sent'],
                'received' => $result['received'],
                'median' => $median,
                'min' => $rtts[0] ?? null,
                'max' => $rtts ? end($rtts) : null,
                'rtts' => $rtts ?: null,
            ]);

            $target->forceFill([
                'last_probed_at' => $probedAt,
                'last_median' => $median,
                'last_loss' => $loss,
            ]);

            if ($alert = $this->evaluateThresholds($target, $median, $loss, $probedAt)) {
                $alerts[] = $alert;
            }

            $target->saveQuietly();

            $this->line(sprintf(
                '%s (%s) => median %s ms, loss %s%%',
                $target->name,
                $target->host,
                $median === null ? '-' : round($median, 2),
                $loss
            ));
        }

        if ($alerts) {
            $telegram->send(implode("\n\n", $alerts));
        }

        return Command::SUCCESS;
    }

    /**
     * Flip the target's alert state once ALERT_AFTER probes in a row
     * disagree with it. Returns a Telegram message block on a flip.
     */
    protected function evaluateThresholds(LatencyTarget $target, ?float $median, float $loss, $probedAt): ?string
    {
        if (! $target->hasThresholds()) {
            $target->forceFill(['alert_active' => false, 'alert_streak' => 0, 'alert_since' => null]);

            return null;
        }

        $breach = $target->breaches($median, $loss);

        if ($breach === $target->alert_active) {
            $target->alert_streak = 0;

            return null;
        }

        $target->alert_streak++;

        if ($target->alert_streak < LatencyTarget::ALERT_AFTER) {
            return null;
        }

        $target->alert_active = $breach;
        $target->alert_streak = 0;
        $since = $target->alert_since;
        $target->alert_since = $breach ? $probedAt : null;

        if (! $target->notify) {
            return null;
        }

        $limits = collect([
            $target->latency_threshold !== null ? "latency &gt; {$target->latency_threshold} ms" : null,
            $target->loss_threshold !== null ? "loss ≥ {$target->loss_threshold}%" : null,
        ])->filter()->implode(' or ');

        $block = ($breach ? '🔴 <b>LATENCY THRESHOLD CROSSED</b>' : '🟢 <b>LATENCY BACK TO NORMAL</b>')
            . "\n🎯 <b>" . e($target->name) . '</b> <code>' . e($target->host) . '</code>'
            . "\n⏱ Median: <b>" . ($median === null ? 'no reply' : round($median, 2) . ' ms') . '</b> · Loss: <b>' . $loss . '%</b>'
            . "\n📏 Alert when {$limits}";

        if ($target->group) {
            $block .= "\n📍 " . e($target->group);
        }

        if (! $breach && $since) {
            $block .= "\n⌛ Lasted " . $since->diffForHumans($probedAt, true);
        }

        return $block . "\n🕒 " . $probedAt->format('d M Y, h:i A');
    }
}
