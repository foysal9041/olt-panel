<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccountsDashboardController extends Controller
{
    public function index()
    {
        $monthStart = Carbon::today()->startOfMonth();
        $monthEnd = Carbon::today()->endOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonth();
        $lastMonthEnd = $lastMonthStart->copy()->endOfMonth();

        $monthlyIncome = (float) Transaction::income()
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $monthlyExpense = (float) Transaction::expense()
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $lastMonthIncome = (float) Transaction::income()
            ->whereBetween('transaction_date', [$lastMonthStart->toDateString(), $lastMonthEnd->toDateString()])
            ->sum('amount');

        $lastMonthExpense = (float) Transaction::expense()
            ->whereBetween('transaction_date', [$lastMonthStart->toDateString(), $lastMonthEnd->toDateString()])
            ->sum('amount');

        $monthlyInvoices = Invoice::with('payments')
            ->whereYear('billing_month', $monthStart->year)
            ->whereMonth('billing_month', $monthStart->month)
            ->get();

        // Partial payments count as collected, same as the Invoices page.
        $totalInvoiced = (float) $monthlyInvoices->sum('amount');
        $totalCollected = (float) $monthlyInvoices->sum(fn (Invoice $invoice) => $invoice->amountPaid());
        $totalOutstanding = max(0.0, $totalInvoiced - $totalCollected);
        $collectionRate = $totalInvoiced > 0 ? round($totalCollected / $totalInvoiced * 100, 1) : 0;

        $invoiceStatus = [
            'paid' => $monthlyInvoices->where('status', 'paid')->count(),
            'partially_paid' => $monthlyInvoices->where('status', 'partially_paid')->count(),
            'unpaid' => $monthlyInvoices->where('status', 'unpaid')->count(),
        ];

        // All-time dues across every open invoice, largest first.
        $openInvoices = Invoice::with(['payments', 'customer:id,name,phone,zone'])
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->get();

        $totalReceivable = (float) $openInvoices->sum(fn (Invoice $invoice) => $invoice->remainingDue());

        $topDues = $openInvoices
            ->groupBy('customer_id')
            ->map(fn ($invoices) => [
                'customer' => $invoices->first()->customer,
                'due' => (float) $invoices->sum(fn (Invoice $invoice) => $invoice->remainingDue()),
                'invoices' => $invoices->count(),
                'oldest' => $invoices->min('billing_month'),
            ])
            ->filter(fn ($row) => $row['customer'] && $row['due'] > 0)
            ->sortByDesc('due')
            ->take(6)
            ->values();

        $recentTransactions = Transaction::with('category')
            ->latest('transaction_date')
            ->latest('id')
            ->limit(8)
            ->get();

        $expenseByCategory = Transaction::query()
            ->join('transaction_categories', 'transaction_categories.id', '=', 'transactions.transaction_category_id')
            ->where('transaction_categories.type', 'expense')
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->groupBy('transaction_categories.name')
            ->orderByDesc(DB::raw('SUM(transactions.amount)'))
            ->limit(5)
            ->get(['transaction_categories.name', DB::raw('SUM(transactions.amount) as total')]);

        $activeCustomers = Customer::where('status', true)->count();
        $activeProducts = Product::where('status', true)->count();

        $trend = $this->sixMonthTrend();

        return view('accounts.dashboard', compact(
            'monthStart',
            'monthlyIncome',
            'monthlyExpense',
            'lastMonthIncome',
            'lastMonthExpense',
            'totalInvoiced',
            'totalCollected',
            'totalOutstanding',
            'collectionRate',
            'invoiceStatus',
            'totalReceivable',
            'topDues',
            'recentTransactions',
            'expenseByCategory',
            'activeCustomers',
            'activeProducts',
            'trend'
        ));
    }

    /**
     * @return array<int, array{label: string, income: float, expense: float}>
     */
    private function sixMonthTrend(): array
    {
        $trend = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::today()->subMonths($i)->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $income = Transaction::income()
                ->whereBetween('transaction_date', [$month->toDateString(), $end->toDateString()])
                ->sum('amount');

            $expense = Transaction::expense()
                ->whereBetween('transaction_date', [$month->toDateString(), $end->toDateString()])
                ->sum('amount');

            $trend[] = [
                'label' => $month->format('M Y'),
                'income' => (float) $income,
                'expense' => (float) $expense,
            ];
        }

        return $trend;
    }
}
