<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly books, laid out like the office's Excel sheets:
 *  - ledger():  one খাত (e.g. আপ্যায়ন, তেল) for a month, day 1..31
 *  - summary(): day-by-day মোট ইনকাম / মোট খরচ / জমা, plus totals per খাত
 */
class AccountReportController extends Controller
{
    public function ledger(Request $request)
    {
        $month = $this->month($request->query('month'));
        $categories = TransactionCategory::orderBy('type')->orderBy('name')->get();
        $category = $categories->firstWhere('id', (int) $request->query('category'))
            ?? $categories->firstWhere('type', 'expense')
            ?? $categories->first();

        $days = [];
        $total = 0.0;

        if ($category) {
            $entries = Transaction::where('transaction_category_id', $category->id)
                ->whereBetween('transaction_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get()
                ->groupBy(fn ($t) => $t->transaction_date->toDateString());

            for ($d = $month->copy(); $d->lte($month->copy()->endOfMonth()); $d->addDay()) {
                $list = $entries->get($d->toDateString(), collect());
                $amount = (float) $list->sum('amount');
                $total += $amount;

                $days[] = [
                    'date' => $d->copy(),
                    'description' => $list->pluck('description')->filter()->implode(', '),
                    'amount' => $amount,
                    'count' => $list->count(),
                ];
            }
        }

        $view = $request->boolean('print') ? 'accounts.reports.ledger-print' : 'accounts.reports.ledger';

        return view($view, compact('month', 'categories', 'category', 'days', 'total'));
    }

    public function summary(Request $request)
    {
        $month = $this->month($request->query('month'));
        $end = $month->copy()->endOfMonth();

        $rows = DB::table('transactions')
            ->join('transaction_categories as c', 'c.id', '=', 'transactions.transaction_category_id')
            ->whereBetween('transactions.transaction_date', [$month->toDateString(), $end->toDateString()])
            ->groupBy('transactions.transaction_date', 'c.type')
            ->get([
                'transactions.transaction_date as d',
                'c.type',
                DB::raw('SUM(transactions.amount) as total'),
            ]);

        $byDay = [];
        foreach ($rows as $r) {
            $byDay[Carbon::parse($r->d)->toDateString()][$r->type] = (float) $r->total;
        }

        $days = [];
        $totalIncome = $totalExpense = 0.0;
        for ($d = $month->copy(); $d->lte($end); $d->addDay()) {
            $inc = $byDay[$d->toDateString()]['income'] ?? 0.0;
            $exp = $byDay[$d->toDateString()]['expense'] ?? 0.0;
            $totalIncome += $inc;
            $totalExpense += $exp;
            $days[] = ['date' => $d->copy(), 'income' => $inc, 'expense' => $exp, 'net' => $inc - $exp];
        }

        $byCategory = DB::table('transactions')
            ->join('transaction_categories as c', 'c.id', '=', 'transactions.transaction_category_id')
            ->whereBetween('transactions.transaction_date', [$month->toDateString(), $end->toDateString()])
            ->groupBy('c.id', 'c.name', 'c.type')
            ->orderBy('c.type')
            ->orderByDesc(DB::raw('SUM(transactions.amount)'))
            ->get(['c.id', 'c.name', 'c.type', DB::raw('SUM(transactions.amount) as total'), DB::raw('COUNT(*) as entries')]);

        $opening = \App\Services\CashBalance::before($month)['amount'];

        $view = $request->boolean('print') ? 'accounts.reports.summary-print' : 'accounts.reports.summary';

        return view($view, compact('month', 'days', 'totalIncome', 'totalExpense', 'byCategory', 'opening'));
    }

    protected function month(?string $value): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : Carbon::today()->startOfMonth();
        } catch (\Throwable) {
            return Carbon::today()->startOfMonth();
        }
    }
}
