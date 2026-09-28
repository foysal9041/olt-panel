<?php

namespace App\Services;

use App\Models\LatencyTarget;
use App\Models\NetworkSwitch;
use App\Models\NttnLink;
use App\Models\Olt;
use App\Models\SwitchPort;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Network health shared by the main dashboard and the NOC dashboard:
 * what the user may see, and the list of things that need attention.
 */
class NocOverview
{
    public function __construct(protected User $user)
    {
    }

    public function isPrivileged(): bool
    {
        return in_array(strtolower($this->user->role), ['admin', 'noc']);
    }

    /**
     * OLTs this user may see (non-privileged users only get their zone).
     */
    public function olts(): Collection
    {
        return Olt::query()
            ->when(! $this->isPrivileged(), fn ($q) => $q->where('zone', $this->user->zone))
            ->orderBy('status')
            ->orderBy('name')
            ->get();
    }

    /**
     * Everything currently wrong, most serious first.
     * Each entry: [severity (danger|warning), icon, title, detail, url].
     *
     * @return list<array{0:string,1:string,2:string,3:?string,4:string}>
     */
    public function issues(?Collection $olts = null): array
    {
        $issues = [];

        if (Gate::forUser($this->user)->allows('access-olt')) {
            $canManageOlt = Gate::forUser($this->user)->allows('access-olt-manage');

            foreach (($olts ?? $this->olts())->where('status', 0) as $o) {
                $issues[] = ['danger', 'fas fa-network-wired', "OLT {$o->name} is offline", trim("{$o->ip} · {$o->zone}", ' ·'),
                    $canManageOlt ? route('olt.show', $o) : route('olt.dashboard')];
            }
        }

        if (Gate::forUser($this->user)->allows('access-olt-switches')) {
            foreach (NetworkSwitch::where('is_active', true)->where('status', 0)->get(['id', 'name', 'ip']) as $s) {
                $issues[] = ['danger', 'fas fa-server', "Switch {$s->name} is not responding", $s->ip, route('switches.show', $s)];
            }

            // Ports that went down in the last 24h and are still down.
            $downPorts = SwitchPort::with('networkSwitch:id,name')
                ->where('admin_status', 1)
                ->where('oper_status', '!=', SwitchPort::UP)
                ->where('last_change_at', '>=', now()->subDay())
                ->latest('last_change_at')
                ->limit(10)
                ->get();

            foreach ($downPorts as $p) {
                $issues[] = ['danger', 'fas fa-plug', "Port {$p->label} down on {$p->networkSwitch?->name}",
                    trim(($p->alias ? $p->alias . ' · ' : '') . 'since ' . $p->last_change_at->diffForHumans()),
                    route('switches.ports.show', [$p->network_switch_id, $p->id])];
            }

            foreach (SwitchPort::with('networkSwitch:id,name')->where('rx_alarm', true)->limit(10)->get() as $p) {
                $issues[] = ['warning', 'fas fa-lightbulb', "Low Rx on {$p->label} ({$p->networkSwitch?->name})",
                    "{$p->rx_power} dBm" . ($p->alias ? " · {$p->alias}" : ''),
                    route('switches.ports.show', [$p->network_switch_id, $p->id])];
            }
        }

        if (Gate::forUser($this->user)->allows('access-olt-nttn')) {
            foreach (NttnLink::where('monitor', true)->where('status', 'active')->where('link_state', 0)->get() as $l) {
                $issues[] = ['danger', 'fas fa-project-diagram', "NTTN link {$l->link_id} is down",
                    trim("{$l->location} · no reply from {$l->pingTarget()} · since " . $l->state_changed_at?->diffForHumans(), ' ·'),
                    route('nttn-links.show', $l)];
            }
        }

        if (Gate::forUser($this->user)->allows('access-latency-graphs')) {
            foreach (LatencyTarget::where('is_active', true)->get() as $t) {
                if ($t->status === 'down') {
                    $issues[] = ['danger', 'fas fa-wave-square', "{$t->name} is unreachable", "{$t->host} · 100% loss", route('latency.show', $t)];
                } elseif ($t->alert_active) {
                    $issues[] = ['warning', 'fas fa-wave-square', "{$t->name} over latency threshold",
                        round((float) $t->last_median, 1) . " ms (limit {$t->latency_threshold} ms) · since " . $t->alert_since?->diffForHumans(),
                        route('latency.show', $t)];
                }
            }
        }

        usort($issues, fn ($a, $b) => ($a[0] === 'danger' ? 0 : 1) <=> ($b[0] === 'danger' ? 0 : 1));

        return $issues;
    }
}
