<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\LatencyTarget;
use App\Models\NetworkSwitch;
use App\Models\Olt;
use App\Models\SwitchEvent;
use App\Models\SwitchPort;
use App\Services\AttendanceCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __construct(private AttendanceCalculator $calculator)
    {
    }

    public function index()
    {
        $user = auth()->user();
        $isPrivileged = in_array(strtolower($user->role), ['admin', 'noc']);

        $can = [
            'olt' => Gate::allows('access-olt'),
            'switches' => Gate::allows('access-olt-switches'),
            'latency' => Gate::allows('access-latency-graphs'),
            'attendance' => Gate::allows('access-attendance'),
            'accounts' => Gate::allows('access-accounts-dashboard'),
        ];

        // Each entry: [severity (danger|warning), icon, title, detail, url]
        $issues = [];

        $olt = null;
        if ($can['olt']) {
            $olts = Olt::query()
                ->when(! $isPrivileged, fn ($q) => $q->where('zone', $user->zone))
                ->get(['id', 'name', 'ip', 'zone', 'status']);

            $olt = [
                'total' => $olts->count(),
                'online' => $olts->where('status', 1)->count(),
                'offline' => $olts->where('status', 0)->count(),
            ];

            $canManageOlt = Gate::allows('access-olt-manage');

            foreach ($olts->where('status', 0) as $o) {
                $issues[] = ['danger', 'fas fa-network-wired', "OLT {$o->name} is offline", trim("{$o->ip} · {$o->zone}", ' ·'),
                    $canManageOlt ? route('olt.show', $o) : route('olt.dashboard')];
            }
        }

        $switches = null;
        $events = collect();
        if ($can['switches']) {
            $all = NetworkSwitch::where('is_active', true)->get(['id', 'name', 'ip', 'status']);

            $switches = [
                'total' => $all->count(),
                'up' => $all->where('status', 1)->count(),
                'down' => $all->where('status', 0)->count(),
                'ports_up' => SwitchPort::where('oper_status', SwitchPort::UP)->count(),
                'ports' => SwitchPort::count(),
                'rx_alarms' => SwitchPort::where('rx_alarm', true)->count(),
            ];

            foreach ($all->where('status', 0) as $s) {
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
                    trim(($p->alias ? $p->alias . ' · ' : '') . 'since ' . $p->last_change_at->diffForHumans()), route('switches.show', $p->network_switch_id)];
            }

            foreach (SwitchPort::with('networkSwitch:id,name')->where('rx_alarm', true)->limit(10)->get() as $p) {
                $issues[] = ['warning', 'fas fa-lightbulb', "Low Rx on {$p->label} ({$p->networkSwitch?->name})",
                    "{$p->rx_power} dBm" . ($p->alias ? " · {$p->alias}" : ''), route('switches.show', $p->network_switch_id)];
            }

            $events = SwitchEvent::with('networkSwitch:id,name')->latest('occurred_at')->latest('id')->limit(8)->get();
        }

        $latency = null;
        if ($can['latency']) {
            $targets = LatencyTarget::where('is_active', true)->orderBy('group')->orderBy('name')->get();

            $latency = [
                'total' => $targets->count(),
                'ok' => $targets->filter(fn ($t) => in_array($t->status, ['up', 'degraded']))->count(),
                'targets' => $targets,
            ];

            foreach ($targets as $t) {
                if ($t->status === 'down') {
                    $issues[] = ['danger', 'fas fa-wave-square', "{$t->name} is unreachable", "{$t->host} · 100% loss", route('latency.show', $t)];
                } elseif ($t->alert_active) {
                    $issues[] = ['warning', 'fas fa-wave-square', "{$t->name} over latency threshold",
                        round((float) $t->last_median, 1) . " ms (limit {$t->latency_threshold} ms) · since " . $t->alert_since?->diffForHumans(), route('latency.show', $t)];
                }
            }
        }

        $attendance = null;
        if ($can['attendance']) {
            $employees = Employee::whereNotNull('device_user_id')->with('dutyShift')
                ->when(! $isPrivileged, fn ($q) => $q->where('zone', $user->zone));

            $attendance = $this->calculator->todaySummary($employees->get(), AttendanceSetting::current());
        }

        $accounts = null;
        if ($can['accounts']) {
            $month = Carbon::today();
            $invoices = Invoice::with('payments')
                ->whereYear('billing_month', $month->year)
                ->whereMonth('billing_month', $month->month)
                ->get(['id', 'amount', 'status']);

            // Partial payments count as collected, same as the Invoices page.
            $accounts = [
                'invoiced' => (float) $invoices->sum('amount'),
                'collected' => (float) $invoices->sum(fn (Invoice $invoice) => $invoice->amountPaid()),
                'count' => $invoices->count(),
                'unpaid' => $invoices->where('status', '!=', 'paid')->count(),
            ];
            $accounts['outstanding'] = $accounts['invoiced'] - $accounts['collected'];
        }

        // Most serious first
        usort($issues, fn ($a, $b) => ($a[0] === 'danger' ? 0 : 1) <=> ($b[0] === 'danger' ? 0 : 1));

        return view('dashboard', compact('can', 'olt', 'switches', 'events', 'latency', 'attendance', 'accounts', 'issues'));
    }
}
