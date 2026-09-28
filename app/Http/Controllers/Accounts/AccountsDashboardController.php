<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class AccountsDashboardController extends Controller
{
    public function index()
    {
        $monthStart = Carbon::today()->startOfMonth();
        $monthEnd = Carbon::today()->endOfMonth();

        $monthlyIncome = Transaction::income()
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $monthlyExpense = Transaction::expense()
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $monthlyInvoices = Invoice::whereYear('billing_month', $monthStart->year)
            ->whereMonth('billing_month', $monthStart->month)
            ->get();

        $totalInvoiced = $monthlyInvoices->sum('amount');
        $totalCollected = $monthlyInvoices->where('status', 'paid')->sum('amount');
        $totalOutstanding = $totalInvoiced - $totalCollected;

        $activeCustomers = Customer::where('status', true)->count();
        $activeProducts = Product::where('status', true)->count();

        $trend = $this->sixMonthTrend();

        return view('accounts.dashboard', compact(
            'monthlyIncome',
            'monthlyExpense',
            'totalInvoiced',
            'totalCollected',
            'totalOutstanding',
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
