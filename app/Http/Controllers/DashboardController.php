<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\IpBlock;
use App\Models\NttnLink;
use App\Models\LatencyTarget;
use App\Models\NetworkSwitch;
use App\Models\Olt;
use App\Models\SwitchEvent;
use App\Models\SwitchPort;
use App\Services\AttendanceCalculator;
use App\Services\NocOverview;
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
            'nttn' => Gate::allows('access-olt-nttn'),
            'ip' => Gate::allows('access-olt-ip'),
        ];

        $overview = new NocOverview($user);
        $issues = $overview->issues();

        $olt = null;
        if ($can['olt']) {
            $olts = $overview->olts();

            $olt = [
                'total' => $olts->count(),
                'online' => $olts->where('status', 1)->count(),
                'offline' => $olts->where('status', 0)->count(),
            ];
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

        $nttn = null;
        if ($can['nttn']) {
            $links = NttnLink::where('status', 'active')->get();
            $monitored = $links->filter(fn (NttnLink $l) => $l->pingTarget() !== null);

            $nttn = [
                'total' => $links->count(),
                'monitored' => $monitored->count(),
                'up' => $monitored->where('link_state', 1)->count(),
                'down' => $monitored->where('link_state', 0)->count(),
                'no_ip' => $links->count() - $monitored->count(),
            ];
        }

        $ip = null;
        if ($can['ip']) {
            $blocks = IpBlock::with('allocations')->orderBy('cidr')->get();
            $size = $blocks->sum(fn (IpBlock $b) => $b->size());
            $used = $blocks->sum(fn (IpBlock $b) => $b->usedCount($b->allocations));

            $ip = [
                'blocks' => $blocks,
                'size' => $size,
                'used' => $used,
                'subnets' => $blocks->sum(fn (IpBlock $b) => $b->allocations->count()),
            ];
        }

        // One-click shortcuts, only for what this user may do.
        $actions = array_values(array_filter([
            Gate::allows('access-olt-manage') ? ['Add OLT', 'fas fa-network-wired', route('olt.create'), 'indigo'] : null,
            $can['switches'] ? ['Add Switch', 'fas fa-server', route('switches.create'), 'sky'] : null,
            $can['nttn'] ? ['Add NTTN Link', 'fas fa-project-diagram', route('nttn-links.create'), 'violet'] : null,
            $can['ip'] ? ['Allocate IP', 'fas fa-globe', route('ip-pools.create'), 'green'] : null,
            Gate::allows('access-latency-targets') ? ['Latency Target', 'fas fa-wave-square', route('latency.targets.create'), 'violet'] : null,
            Gate::allows('access-accounts-invoices') ? ['Receive Payment', 'fas fa-hand-holding-usd', route('accounts.payments.create'), 'green'] : null,
            Gate::allows('access-accounts-transactions') ? ['Add Transaction', 'fas fa-exchange-alt', route('accounts.transactions.create'), 'amber'] : null,
            Gate::allows('access-accounts-invoices') ? ['Invoices', 'fas fa-file-invoice', route('accounts.invoices.index'), 'amber'] : null,
            Gate::allows('access-attendance-leaves') ? ['Add Leave', 'fas fa-plane-departure', route('attendance.leaves.create'), 'rose'] : null,
        ]));

        return view('dashboard', compact('can', 'olt', 'switches', 'events', 'latency', 'attendance', 'accounts', 'issues', 'nttn', 'ip', 'actions'));
    }
}
