<?php

namespace App\Services;

use App\Models\NetworkSwitch;
use App\Models\NocAlertSetting;
use App\Models\SwitchEvent;
use App\Models\SwitchPort;
use App\Services\Snmp\SnmpClient;
use App\Services\Snmp\TransceiverReader;
use Illuminate\Support\Carbon;

/**
 * Polls one switch over SNMP: reachability, interface list and status
 * (IF-MIB), transceiver DOM readings, and turns changes into SwitchEvents
 * plus a single combined Telegram message.
 */
class SwitchPoller
{
    // SNMPv2-MIB system group
    protected const SYS_DESCR = '1.3.6.1.2.1.1.1.0';
    protected const SYS_UPTIME = '1.3.6.1.2.1.1.3.0';
    protected const SYS_NAME = '1.3.6.1.2.1.1.5.0';

    // IF-MIB
    protected const IF_DESCR = '1.3.6.1.2.1.2.2.1.2';
    protected const IF_TYPE = '1.3.6.1.2.1.2.2.1.3';
    protected const IF_SPEED = '1.3.6.1.2.1.2.2.1.5';
    protected const IF_ADMIN_STATUS = '1.3.6.1.2.1.2.2.1.7';
    protected const IF_OPER_STATUS = '1.3.6.1.2.1.2.2.1.8';
    protected const IF_NAME = '1.3.6.1.2.1.31.1.1.1.1';
    protected const IF_HIGH_SPEED = '1.3.6.1.2.1.31.1.1.1.15';
    protected const IF_ALIAS = '1.3.6.1.2.1.31.1.1.1.18';

    /**
     * ifTypes kept as "ports": ethernetCsmacd, fastEther, fastEtherFX,
     * gigabitEthernet, ieee8023adLag. VLAN interfaces, loopbacks,
     * tunnels, etc. are skipped.
     */
    protected const PORT_TYPES = [6, 62, 69, 117, 161];

    /** A switch must miss this many polls in a row before it's DOWN. */
    protected const FAILS_BEFORE_DOWN = 2;

    /** Rx must climb this many dB above the threshold to clear an alarm. */
    protected const RX_HYSTERESIS = 1.0;

    /** @var list<array{event: SwitchEvent, notify: bool}> */
    protected array $events = [];

    public function __construct(
        protected TransceiverReader $transceivers,
        protected TelegramNotifier $telegram,
    ) {
    }

    /**
     * @return array{ok:bool, ports:int, transceivers:int, events:int, error:?string}
     */
    public function poll(NetworkSwitch $switch): array
    {
        $this->events = [];
        $settings = NocAlertSetting::current();
        $now = now();

        try {
            $snmp = new SnmpClient($switch);
            $system = $snmp->get([self::SYS_NAME, self::SYS_DESCR, self::SYS_UPTIME]);
        } catch (\Throwable $e) {
            $this->markUnreachable($switch, $e->getMessage(), $now, $settings);
            $this->notify($switch, $settings);

            return ['ok' => false, 'ports' => 0, 'transceivers' => 0, 'events' => count($this->events), 'error' => $e->getMessage()];
        }

        try {
            $this->markReachable($switch, $system, $now, $settings);

            $ports = $this->syncPorts($snmp, $switch, $now, $settings);
            $domCount = $this->syncTransceivers($snmp, $switch, $ports, $now, $settings);

            $switch->last_error = null;
            $switch->save();
        } catch (\Throwable $e) {
            // Reachable but the interface walk failed half way (e.g. timeout
            // on a big table). Keep the old port data; report the error.
            $switch->last_error = $e->getMessage();
            $switch->save();

            $this->notify($switch, $settings);

            return ['ok' => false, 'ports' => 0, 'transceivers' => 0, 'events' => count($this->events), 'error' => $e->getMessage()];
        } finally {
            $snmp->close();
        }

        $this->notify($switch, $settings);

        return [
            'ok' => true,
            'ports' => count($ports),
            'transceivers' => $domCount,
            'events' => count($this->events),
            'error' => null,
        ];
    }

    // ---- Reachability -------------------------------------------------------

    protected function markUnreachable(NetworkSwitch $switch, string $error, Carbon $now, NocAlertSetting $settings): void
    {
        $switch->fail_count = min(255, $switch->fail_count + 1);
        $switch->last_error = $error;
        $switch->last_polled_at = $now;

        if ($switch->fail_count >= self::FAILS_BEFORE_DOWN && $switch->status !== 0) {
            // Only alert on a real transition, not a switch that has never answered.
            if ($switch->status === 1) {
                $this->event($switch, null, 'switch_down', "Switch {$switch->name} ({$switch->ip}) is not responding to SNMP", $now, $settings->alert_switch_status);
            }

            $switch->status = 0;
        }

        $switch->save();
    }

    protected function markReachable(NetworkSwitch $switch, array $system, Carbon $now, NocAlertSetting $settings): void
    {
        // A switch that has never answered before isn't "back" — it's new.
        if ($switch->status === 0 && $switch->ports()->exists()) {
            $this->event($switch, null, 'switch_up', "Switch {$switch->name} ({$switch->ip}) is reachable again", $now, $settings->alert_switch_status);
        }

        $switch->status = 1;
        $switch->fail_count = 0;
        $switch->last_polled_at = $now;
        $switch->sys_name = $this->clean($system[self::SYS_NAME] ?? null);
        $switch->sys_descr = $this->clean($system[self::SYS_DESCR] ?? null, 1000);
        $switch->uptime_seconds = $this->ticksToSeconds($system[self::SYS_UPTIME] ?? null);
    }

    // ---- Ports --------------------------------------------------------------

    /**
     * @return array<int, SwitchPort> keyed by ifIndex
     */
    protected function syncPorts(SnmpClient $snmp, NetworkSwitch $switch, Carbon $now, NocAlertSetting $settings): array
    {
        $descr = $snmp->walk(self::IF_DESCR);
        $names = $snmp->walk(self::IF_NAME);

        if (! $descr && ! $names) {
            throw new \RuntimeException('Switch answered but returned no interfaces (check SNMP view / community permissions).');
        }

        $types = $snmp->walk(self::IF_TYPE);
        $admin = $snmp->walk(self::IF_ADMIN_STATUS);
        $oper = $snmp->walk(self::IF_OPER_STATUS);
        $alias = $snmp->tryWalk(self::IF_ALIAS);
        $highSpeed = $snmp->tryWalk(self::IF_HIGH_SPEED);
        $speed = $highSpeed ? [] : $snmp->tryWalk(self::IF_SPEED);

        $existing = $switch->ports()->get()->keyBy('if_index');
        $seen = [];
        $ports = [];

        foreach (array_keys($descr + $names) as $ifIndex) {
            $ifIndex = (int) $ifIndex;
            $type = isset($types[$ifIndex]) ? (int) $types[$ifIndex] : null;

            if ($type !== null && ! in_array($type, self::PORT_TYPES, true)) {
                continue;
            }

            $seen[] = $ifIndex;

            $port = $existing[$ifIndex] ?? new SwitchPort([
                'network_switch_id' => $switch->id,
                'if_index' => $ifIndex,
            ]);

            $newOper = isset($oper[$ifIndex]) ? (int) $oper[$ifIndex] : null;
            $newAdmin = isset($admin[$ifIndex]) ? (int) $admin[$ifIndex] : null;
            $wasUp = $port->oper_status === SwitchPort::UP;
            $isUp = $newOper === SwitchPort::UP;

            $port->fill([
                'name' => $this->clean($names[$ifIndex] ?? null),
                'descr' => $this->clean($descr[$ifIndex] ?? null),
                'alias' => $this->clean($alias[$ifIndex] ?? null),
                'if_type' => $type,
                'admin_status' => $newAdmin,
                'speed_mbps' => isset($highSpeed[$ifIndex])
                    ? (int) $highSpeed[$ifIndex]
                    : (isset($speed[$ifIndex]) ? intdiv((int) $speed[$ifIndex], 1_000_000) : null),
            ]);

            // Only alert on changes to a port we already knew about.
            if ($port->exists && $port->oper_status !== null && $newOper !== null && $wasUp !== $isUp) {
                $port->last_change_at = $now;
                $port->oper_status = $newOper;
                $port->save();

                $adminNote = $newAdmin === 2 ? ' (administratively shut down)' : '';

                $this->event(
                    $switch,
                    $port,
                    $isUp ? 'port_up' : 'port_down',
                    "Port {$this->portLabel($port)} on {$switch->name} is " . ($isUp ? 'UP' : 'DOWN') . $adminNote,
                    $now,
                    $settings->alert_port_status && $port->notify
                );
            } else {
                $port->oper_status = $newOper;
                $port->save();
            }

            $ports[$ifIndex] = $port;
        }

        // Interfaces that disappeared (line card removed, renumbered, ...)
        $switch->ports()->whereNotIn('if_index', $seen)->delete();

        return $ports;
    }

    // ---- Transceivers -------------------------------------------------------

    /**
     * @param  array<int, SwitchPort>  $ports
     */
    protected function syncTransceivers(SnmpClient $snmp, NetworkSwitch $switch, array $ports, Carbon $now, NocAlertSetting $settings): int
    {
        $portInfo = array_map(fn (SwitchPort $p) => ['name' => $p->name, 'descr' => $p->descr], $ports);

        $readings = $this->transceivers->read($snmp, $switch, $portInfo);

        // Nothing came back at all — could be a transient failure, so keep
        // what we had rather than wiping every port's readings.
        if (! $readings) {
            return 0;
        }

        $threshold = $settings->rx_low_threshold;

        foreach ($ports as $ifIndex => $port) {
            $dom = $readings[$ifIndex] ?? [];

            $port->fill([
                'rx_power' => isset($dom['rx_power']) ? round($dom['rx_power'], 2) : null,
                'tx_power' => isset($dom['tx_power']) ? round($dom['tx_power'], 2) : null,
                'temperature' => isset($dom['temperature']) ? round($dom['temperature'], 1) : null,
                'voltage' => isset($dom['voltage']) ? round($dom['voltage'], 3) : null,
                'bias' => isset($dom['bias']) ? round($dom['bias'], 2) : null,
                'rx_high_alarm' => $dom['rx_high_alarm'] ?? null,
                'rx_high_warn' => $dom['rx_high_warn'] ?? null,
                'rx_low_warn' => $dom['rx_low_warn'] ?? null,
                'rx_low_alarm' => $dom['rx_low_alarm'] ?? null,
                'dom_updated_at' => $dom ? $now : $port->dom_updated_at,
            ]);

            $this->checkRxAlarm($switch, $port, $port->rxAlertThreshold($threshold), $now, $settings);

            $port->save();
        }

        return count($readings);
    }

    protected function checkRxAlarm(NetworkSwitch $switch, SwitchPort $port, ?float $threshold, Carbon $now, NocAlertSetting $settings): void
    {
        if ($threshold === null || $port->rx_power === null || $port->oper_status !== SwitchPort::UP) {
            // Nothing to judge (no threshold, no module, or link down —
            // the port_down alert already covers that case).
            $port->rx_alarm = $threshold !== null && $port->rx_alarm && $port->rx_power !== null && $port->rx_power < $threshold;

            return;
        }

        if (! $port->rx_alarm && $port->rx_power < $threshold) {
            $port->rx_alarm = true;

            $source = $port->rx_low_warn !== null ? 'module low warning' : 'threshold';

            $this->event($switch, $port, 'rx_low',
                "Low Rx power on {$this->portLabel($port)} ({$switch->name}): {$port->rx_power} dBm ({$source} {$threshold} dBm)",
                $now, $port->notify);
        } elseif ($port->rx_alarm && $port->rx_power >= $threshold + self::RX_HYSTERESIS) {
            $port->rx_alarm = false;

            $this->event($switch, $port, 'rx_normal',
                "Rx power back to normal on {$this->portLabel($port)} ({$switch->name}): {$port->rx_power} dBm",
                $now, $port->notify);
        }
    }

    // ---- Events & notifications --------------------------------------------

    protected function event(NetworkSwitch $switch, ?SwitchPort $port, string $type, string $message, Carbon $now, bool $shouldNotify): void
    {
        $event = SwitchEvent::create([
            'network_switch_id' => $switch->id,
            'switch_port_id' => $port?->id,
            'type' => $type,
            'message' => mb_substr($message, 0, 255),
            'occurred_at' => $now,
        ]);

        $event->setRelation('port', $port);

        $this->events[] = ['event' => $event, 'notify' => $shouldNotify && $switch->notify];
    }

    /**
     * One Telegram message per poll, listing every alert-worthy change.
     */
    protected function notify(NetworkSwitch $switch, NocAlertSetting $settings): void
    {
        $toSend = array_column(array_filter($this->events, fn ($e) => $e['notify']), 'event');

        if (! $toSend || ! $settings->telegram_enabled) {
            return;
        }

        $icons = [
            'port_down' => '🔴', 'port_up' => '🟢',
            'switch_down' => '🚨', 'switch_up' => '✅',
            'rx_low' => '⚠️', 'rx_normal' => '🔵',
        ];

        $lines = [];

        foreach ($toSend as $event) {
            $port = $event->port;
            $title = SwitchEvent::TYPES[$event->type][0];

            $block = ($icons[$event->type] ?? '•') . ' <b>' . e(strtoupper($title)) . '</b>';
            $block .= "\n🖧 <b>" . e($switch->name) . '</b> <code>' . e($switch->ip) . '</code>';

            if ($port) {
                $block .= "\n🔌 Port: <b>" . e($port->name ?: $port->descr) . '</b>';

                if ($port->alias) {
                    $block .= ' — ' . e($port->alias);
                }

                if ($port->rx_power !== null && in_array($event->type, ['rx_low', 'rx_normal', 'port_up'])) {
                    $block .= "\n📶 Rx: <b>{$port->rx_power} dBm</b>" . ($port->tx_power !== null ? " · Tx: {$port->tx_power} dBm" : '');

                    if ($port->rx_low_warn !== null) {
                        $block .= "\n📏 Module limit: warn {$port->rx_low_warn} / alarm {$port->rx_low_alarm} dBm";
                    }
                }
            }

            if ($switch->zone) {
                $block .= "\n📍 " . e($switch->zone);
            }

            $block .= "\n🕒 " . $event->occurred_at->format('d M Y, h:i:s A');

            $lines[] = $block;
        }

        $errors = $this->telegram->send(implode("\n\n", $lines), $settings);

        if (! $errors) {
            SwitchEvent::whereKey(array_map(fn ($e) => $e->id, $toSend))->update(['notified' => true]);
        }
    }

    // ---- Helpers ------------------------------------------------------------

    protected function portLabel(SwitchPort $port): string
    {
        $label = $port->name ?: ($port->descr ?: "ifIndex {$port->if_index}");

        return $port->alias ? "{$label} ({$port->alias})" : $label;
    }

    protected function ticksToSeconds(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/\((\d+)\)/', $value, $m) || preg_match('/^(\d+)$/', trim($value), $m)) {
            return intdiv((int) $m[1], 100);
        }

        return null;
    }

    protected function clean(?string $value, int $max = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(mb_scrub($value, 'UTF-8'), " \"\0");

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
