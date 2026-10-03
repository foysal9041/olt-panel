<?php

namespace App\Services;

use App\Models\NttnLink;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\Pool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;

/**
 * Pings NTTN links and turns results into confirmed up/down state plus
 * Telegram alert blocks. Used by app:check-nttn-links every minute.
 */
class NttnMonitor
{
    public const PINGS = 5;

    public function __construct(protected LatencyProber $prober)
    {
    }

    /**
     * Ping many links in parallel.
     *
     * @param  Collection<int, NttnLink>  $links  (each must have a ping target)
     * @return array<int, array> keyed by link id, same shape as ping()
     */
    public function pingMany(Collection $links): array
    {
        $results = [];

        foreach ($links->chunk(25) as $chunk) {
            try {
                $pool = Process::pool(function (Pool $pool) use ($chunk) {
                    foreach ($chunk as $link) {
                        $pool->as((string) $link->id)->timeout(15)->command($this->command($link->pingTarget()));
                    }
                })->start()->wait();
            } catch (ProcessTimedOutException $e) {
                report($e); // too busy this round — try these links again next time

                continue;
            }

            foreach ($chunk as $link) {
                $out = $pool[(string) $link->id]->output();
                $results[$link->id] = $this->summarise($link->pingTarget(), $out, $out);
            }
        }

        return $results;
    }

    /**
     * Store a ping result on the link and move its confirmed state along.
     * Returns a Telegram message block when the state flips up<->down.
     */
    public function record(NttnLink $link, array $r, Carbon $now): ?string
    {
        $up = $r['received'] > 0;

        $link->forceFill([
            'last_ping_at' => $now,
            'last_ping_ok' => $up,
            'last_ping_rtt' => $r['avg'],
            'last_ping_loss' => $r['loss'],
        ]);

        // First ever check: take the state as-is, nothing to announce.
        if ($link->link_state === null) {
            $link->forceFill(['link_state' => (int) $up, 'state_streak' => 0, 'state_changed_at' => $now])->save();

            return null;
        }

        if ($up === (bool) $link->link_state) {
            $link->state_streak = 0;
            $link->save();

            return null;
        }

        $link->state_streak++;

        if ($link->state_streak < NttnLink::STATE_AFTER) {
            $link->save();

            return null;
        }

        $since = $link->state_changed_at;
        $link->forceFill(['link_state' => (int) $up, 'state_streak' => 0, 'state_changed_at' => $now])->save();

        return $this->message($link, $up, $r, $since, $now);
    }

    protected function message(NttnLink $link, bool $up, array $r, ?Carbon $since, Carbon $now): string
    {
        $block = ($up ? '🟢 <b>NTTN LINK UP</b>' : '🔴 <b>NTTN LINK DOWN</b>')
            . "\n🔗 Link ID: <b>" . e($link->link_id) . '</b>'
            . ($link->provider ? ' (' . e($link->provider) . ')' : '')
            . "\n📍 Location: <b>" . e($link->location) . '</b>';

        if ($link->zone) {
            $block .= "\n🗺 Zone: " . e($link->zone);
        }

        if ($link->bandwidth) {
            $block .= "\n📶 Bandwidth: " . e($link->bandwidth);
        }

        $block .= "\n🎯 Ping <code>" . e($r['target']) . '</code>: '
            . ($up ? "{$r['received']}/{$r['sent']} replies, {$r['avg']} ms" : 'no reply (100% loss)');

        if ($up && $since) {
            $block .= "\n⌛ Was down for " . $since->diffForHumans($now, true);
        }

        return $block . "\n🕒 " . $now->format('d M Y, h:i A');
    }

    protected function command(string $target): array
    {
        return ['ping', '-n', '-c', (string) self::PINGS, '-i', '0.2', '-W', '1', '-w', '4', $target];
    }

    protected function summarise(string $target, string $stdout, string $output): array
    {
        $rtts = $this->prober->parse($stdout, self::PINGS);
        $received = count($rtts);

        return [
            'target' => $target,
            'sent' => self::PINGS,
            'received' => $received,
            'loss' => round((self::PINGS - $received) / self::PINGS * 100),
            'min' => $rtts[0] ?? null,
            'avg' => $received ? round(array_sum($rtts) / $received, 2) : null,
            'max' => $rtts ? end($rtts) : null,
            'output' => $output,
        ];
    }
}
