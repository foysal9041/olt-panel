<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SalarySheet;
use App\Models\Transaction;
use App\Models\ZoneSettlement;
use App\Services\CashBalance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Accounts at a glance: this month's income and expense, cash in hand now,
 * the zone settlements and salary sheets, and where the money came from and
 * went.
 */
class AccountsDashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $month = [$monthStart->toDateString(), $monthStart->copy()->endOfMonth()->toDateString()];
        $lastStart = $monthStart->copy()->subMonthNoOverflow();
        $lastMonth = [$lastStart->toDateString(), $lastStart->copy()->endOfMonth()->toDateString()];

        // Real income and costs: petty cash brought from the bank etc. ("Not counted") stays out.
        $sum = fn (string $type, array $range) => (float) Transaction::{$type}()->counted()->whereBetween('transaction_date', $range)->sum('amount');

        $income = $sum('income', $month);
        $expense = $sum('expense', $month);
        $lastIncome = $sum('income', $lastMonth);
        $lastExpense = $sum('expense', $lastMonth);

        // Cash in hand right now: today's জের plus today's entries.
        ['amount' => $opening, 'base' => $count] = CashBalance::before($today);
        $dayIn = (float) Transaction::income()->cash()->whereDate('transaction_date', $today)->sum('amount');
        $dayOut = (float) Transaction::expense()->cash()->whereDate('transaction_date', $today)->sum('amount');
        $cash = [
            'now' => $opening + $dayIn - $dayOut,
            'in' => $dayIn,
            'out' => $dayOut,
            'counted_on' => $count?->date,
        ];

        $settlements = ZoneSettlement::with('rows')->latest('month')->latest('id')->limit(6)->get()
            ->map(fn (ZoneSettlement $s) => ['model' => $s, 'totals' => $s->totals()]);

        $salary = SalarySheet::withSum('items as net_total', 'net')->withCount('items')->latest('month')->first();
        $profit = \App\Models\ProfitSheet::latest('month')->first();
        $partnersDue = (string) \App\Models\PartnerEntry::sum('amount');

        $byCategory = fn (string $type) => Transaction::query()
            ->join('transaction_categories as c', 'c.id', '=', 'transactions.transaction_category_id')
            ->where('c.type', $type)
            ->where(fn ($q) => $q->whereNull('c.pl_group')->orWhere('c.pl_group', '!=', 'none'))
            ->whereBetween('transaction_date', $month)
            ->groupBy('c.name')
            ->orderByDesc(DB::raw('SUM(transactions.amount)'))
            ->limit(6)
            ->get(['c.name', DB::raw('SUM(transactions.amount) as total'), DB::raw('COUNT(*) as entries')]);

        $recent = Transaction::with('category')->latest('transaction_date')->latest('id')->limit(8)->get();

        $customers = Customer::where('status', true)
            ->selectRaw('customer_type, COUNT(*) as c')->groupBy('customer_type')->pluck('c', 'customer_type');

        return view('accounts.dashboard', [
            'monthStart' => $monthStart,
            'income' => $income,
            'expense' => $expense,
            'lastIncome' => $lastIncome,
            'lastExpense' => $lastExpense,
            'cash' => $cash,
            'settlements' => $settlements,
            'salary' => $salary,
            'profit' => $profit,
            'partnersDue' => $partnersDue,
            'incomeByCategory' => $byCategory('income'),
            'expenseByCategory' => $byCategory('expense'),
            'recent' => $recent,
            'customers' => $customers,
            'trend' => $this->trend(),
        ]);
    }

    /**
     * @return list<array{label: string, income: float, expense: float}>
     */
    private function trend(): array
    {
        return collect(range(5, 0))->map(function (int $i) {
            $start = Carbon::today()->subMonthsNoOverflow($i)->startOfMonth();
            $range = [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];

            return [
                'label' => $start->format('M Y'),
                'income' => (float) Transaction::income()->counted()->whereBetween('transaction_date', $range)->sum('amount'),
                'expense' => (float) Transaction::expense()->counted()->whereBetween('transaction_date', $range)->sum('amount'),
            ];
        })->all();
    }
}
