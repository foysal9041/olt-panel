<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\IpBlock;
use App\Models\NttnLink;
use App\Models\LatencyTarget;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Task;
use App\Models\Ticket;
use App\Services\InventoryStock;
use App\Models\NetworkSwitch;
use App\Models\Olt;
use App\Models\SwitchEvent;
use App\Models\SwitchPort;
use App\Models\Transaction;
use App\Services\AttendanceCalculator;
use App\Services\CashBalance;
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
            $all = NetworkSwitch::visibleTo($user)->where('is_active', true)->get(['id', 'name', 'ip', 'status']);
            $ports = SwitchPort::whereIn('network_switch_id', $all->pluck('id'));

            $switches = [
                'total' => $all->count(),
                'up' => $all->where('status', 1)->count(),
                'down' => $all->where('status', 0)->count(),
                'ports_up' => (clone $ports)->where('oper_status', SwitchPort::UP)->count(),
                'ports' => (clone $ports)->count(),
                'rx_alarms' => (clone $ports)->where('rx_alarm', true)->count(),
            ];

            $events = SwitchEvent::whereIn('network_switch_id', $all->pluck('id'))->with('networkSwitch:id,name')->latest('occurred_at')->latest('id')->limit(20)->get();
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

            $employeeList = $employees->get();
            $settings = AttendanceSetting::current();

            $attendance = $this->calculator->todaySummary($employeeList, $settings);
            $attendance['trend'] = $this->calculator->trend($employeeList, $settings, 7);
        }

        $accounts = null;
        if ($can['accounts']) {
            // Latest zone settlement: its Net Bill and whether it's posted to income.
            $settlement = \App\Models\ZoneSettlement::with('rows')->latest('month')->latest('id')->first();
            $accounts = ['settlement' => $settlement ? [
                'model' => $settlement,
                'income' => $settlement->totals()['income'],
            ] : null];

            // What bandwidth clients still owe, and the latest month's net profit.
            $accounts['bandwidth_due'] = \App\Models\Customer::where('customer_type', 'bandwidth_client')->get()
                ->reduce(fn ($t, $c) => \App\Support\Dec::add($t, $c->bandwidthBalance()), '0');
            $accounts['profit'] = \App\Models\ProfitSheet::latest('month')->first();

            // The office's books: today's cash book and this month's totals.
            $today = Carbon::today();
            ['amount' => $opening, 'base' => $count] = CashBalance::before($today);
            $dayIn = (float) Transaction::income()->cash()->whereDate('transaction_date', $today)->sum('amount');
            $dayOut = (float) Transaction::expense()->cash()->whereDate('transaction_date', $today)->sum('amount');
            $monthRange = [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()];

            $accounts['cash'] = [
                'opening' => $opening,
                'in' => $dayIn,
                'out' => $dayOut,
                'closing' => $opening + $dayIn - $dayOut,
                // Without a cash-on-hand count the balance is just income
                // minus expenses since the first entry — not real cash.
                'counted_on' => $count?->date,
                'month_in' => (float) Transaction::income()->counted()->whereBetween('transaction_date', $monthRange)->sum('amount'),
                'month_out' => (float) Transaction::expense()->counted()->whereBetween('transaction_date', $monthRange)->sum('amount'),
            ];

            // Income vs expense, last 6 months, for the mini chart.
            $accounts['trend'] = collect(range(5, 0))->map(function ($i) {
                $start = Carbon::today()->subMonthsNoOverflow($i)->startOfMonth();
                $range = [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];

                return [
                    'label' => $start->format('M'),
                    'income' => (float) Transaction::income()->counted()->whereBetween('transaction_date', $range)->sum('amount'),
                    'expense' => (float) Transaction::expense()->counted()->whereBetween('transaction_date', $range)->sum('amount'),
                ];
            })->all();
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

        $work = $this->work($user);

        // One-click shortcuts, only for what this user may do.
        $actions = array_values(array_filter([
            Gate::allows('access-olt-manage') ? ['Add OLTs', 'fas fa-network-wired', route('olt.create'), 'indigo'] : null,
            $can['switches'] ? ['Add Switch', 'fas fa-server', route('switches.create'), 'sky'] : null,
            $can['ip'] ? ['Find Free IP', 'fas fa-globe', route('ip-pools.index') . '#finder', 'green'] : null,
            Gate::allows('access-olt-vlans') ? ['Find Free VLAN', 'fas fa-stream', route('vlans.index') . '#finder', 'violet'] : null,
            $can['nttn'] ? ['Add NTTN Link', 'fas fa-project-diagram', route('nttn-links.create'), 'violet'] : null,
            Gate::allows('access-accounts-cashbook') ? ['Cash Book', 'fas fa-book-open', route('accounts.cashbook.index'), 'amber'] : null,
            Gate::allows('access-accounts-settlements') ? ['Zone Settlement', 'fas fa-file-excel', route('accounts.settlements.index'), 'green'] : null,
            Gate::allows('access-accounts-salaries') ? ['Salary Sheet', 'fas fa-money-check-alt', route('accounts.salaries.index'), 'amber'] : null,
            Gate::allows('access-attendance-leaves') ? ['Add Leave', 'fas fa-plane-departure', route('attendance.leaves.create'), 'rose'] : null,
            Gate::allows('access-tickets-manage') ? ['New Ticket', 'fas fa-ticket-alt', route('tickets.create'), 'rose'] : null,
            Gate::allows('access-inventory-stock') ? ['Stock Entry', 'fas fa-boxes', route('inventory.entries.create', ['type' => 'use']), 'sky'] : null,
        ]));

        // What the team has been doing (for those who manage users).
        $team = Gate::allows('access-settings-users')
            ? \App\Models\ActivityLog::with('user:id,name')->latest('id')->limit(25)->get()
            : null;

        return view('dashboard', compact('can', 'olt', 'switches', 'events', 'team', 'latency', 'attendance', 'accounts', 'issues', 'nttn', 'ip', 'actions', 'work'));
    }

    /**
     * Everyone's own work: their to-do list, tasks they gave, tickets given
     * to them, their leave — plus tickets / assets numbers for those who
     * look after them.
     */
    protected function work(\App\Models\User $user): array
    {
        $mine = Task::where('assigned_to', $user->id);
        $employee = $user->employee;

        $leave = null;
        if ($employee) {
            $types = LeaveType::whereNotNull('default_days_per_year')->orderBy('name')->get();
            $leave = [
                'balances' => $types->map(fn ($t) => ['name' => $t->name, 'left' => $employee->remainingLeaveDays($t), 'allocated' => $employee->allocatedLeaveDays($t)]),
                'latest' => $employee->leaves()->with('leaveType')->latest('id')->first(),
                'today' => $employee->leaves()->approved()->overlapping(today()->toDateString(), today()->toDateString())->exists(),
            ];
        }

        return [
            'tasks' => (clone $mine)->with(['creator', 'ticket'])->where(fn ($q) => $q->open()->orWhere('completed_at', '>=', now()->subHours(12)))
                ->workOrder()->limit(8)->get(),
            'open' => (clone $mine)->open()->count(),
            'overdue' => (clone $mine)->open()->whereDate('due_date', '<', today())->count(),
            'given' => Task::with('assignee')->where('created_by', $user->id)->where('assigned_to', '!=', $user->id)
                ->where(fn ($q) => $q->open()->orWhere('completed_at', '>=', now()->subDays(2)))->workOrder()->limit(6)->get(),
            'tickets' => Ticket::with('customer')->where('assigned_to', $user->id)->active()
                ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")->orderBy('due_at')->limit(5)->get(),
            'leave' => $leave,
            'leave_pending' => Gate::allows('access-attendance-leaves') ? Leave::where('status', 'pending')->count() : null,
            'ticket_stats' => Gate::allows('access-tickets-manage') ? [
                'open' => Ticket::active()->count(),
                'overdue' => Ticket::overdue()->count(),
                'unassigned' => Ticket::active()->whereNull('assigned_to')->count(),
            ] : null,
            'assets' => Gate::allows('access-inventory-summary') ? app(InventoryStock::class)->summary() : null,
            'can_assign' => Gate::allows('access-tasks-assign'),
        ];
    }
}
