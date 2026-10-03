<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\CashOpening;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Services\CashBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * প্রতিদিনের হিসাব — one day of জমা (income) and খরচ (expense) side by side,
 * with the balance brought forward (জের) and what's left (অবশিষ্ট টাকা).
 * Entries are ordinary transactions, so they also show up in the monthly
 * ledger sheets, the summary and Income & Expenses.
 */
class CashBookController extends Controller
{
    public function index(Request $request)
    {
        $date = $this->date($request->query('date'));

        $entries = Transaction::with('category', 'recordedBy:id,name')
            ->cash()
            ->whereDate('transaction_date', $date)
            ->orderBy('id')
            ->get();

        $income = $entries->filter(fn ($t) => $t->category?->type === 'income')->values();
        $expense = $entries->filter(fn ($t) => $t->category?->type === 'expense')->values();

        ['amount' => $opening, 'base' => $base] = CashBalance::before($date);
        $countToday = $base && $base->date->isSameDay($date) ? $base : null;
        $dayIncome = (float) $income->sum('amount');
        $dayExpense = (float) $expense->sum('amount');

        $totals = [
            'opening' => $opening,                          // জের
            'day_income' => $dayIncome,                     // দিনের আয়
            'total_income' => $opening + $dayIncome,        // মোট আয়
            'total_expense' => $dayExpense,                 // মোট ব্যয়
            'closing' => $opening + $dayIncome - $dayExpense, // অবশিষ্ট টাকা
        ];

        // জমা: only heads that aren't income (petty cash brought from the bank).
        $categories = TransactionCategory::orderBy('name')->get()
            ->reject(fn ($c) => Transaction::isRealIncome($c))
            ->groupBy('type');

        $view = $request->boolean('print') ? 'accounts.cashbook.print' : 'accounts.cashbook.index';

        $locked = ! Transaction::dayIsOpenFor($date, auth()->user());

        return view($view, compact('date', 'income', 'expense', 'totals', 'categories', 'base', 'countToday', 'locked'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'transaction_category_id' => 'required|exists:transaction_categories,id',
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
        ], [
            'transaction_category_id.required' => 'Choose a head (category).',
        ]);

        abort_unless(Transaction::dayIsOpenFor($validated['transaction_date'], auth()->user()), 403, Transaction::lockReason($validated['transaction_date']));

        // The Cash Book is the petty cash: income belongs in the bank.
        if (Transaction::isRealIncome(TransactionCategory::find($validated['transaction_category_id']))) {
            return back()->withInput()->withErrors(['transaction_category_id' => 'Income is deposited in the bank — record it in Income & Expenses as Bank. Cash in here is only petty cash brought into the office.']);
        }

        $same = Transaction::where('transaction_category_id', $validated['transaction_category_id'])->where('account', 'cash')
            ->where('amount', $validated['amount'])->whereDate('transaction_date', $validated['transaction_date'])
            ->where('description', $validated['description'] ?? null);
        if (\App\Support\DuplicateGuard::recent($same)) {
            return back()->withInput()->with('error', \App\Support\DuplicateGuard::message());
        }

        Transaction::create($validated + [
            'account' => 'cash',
            'recorded_by' => auth()->id(),
            'zone' => auth()->user()->zone,
        ]);

        return redirect()
            ->route('accounts.cashbook.index', ['date' => Carbon::parse($validated['transaction_date'])->toDateString()])
            ->with('success', 'Entry added.');
    }

    public function destroy(Transaction $transaction)
    {
        abort_if($transaction->isLockedFor(auth()->user()), 403, Transaction::lockReason($transaction->transaction_date));

        $date = $transaction->transaction_date->toDateString();
        $transaction->delete();

        return redirect()->route('accounts.cashbook.index', ['date' => $date])->with('success', 'Entry removed.');
    }

    /**
     * Cash on hand (হাতে নগদ) counted at the start of a day; the জের for
     * this and later days counts from here.
     */
    public function setOpening(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'amount' => 'required|numeric',
            'note' => 'nullable|string|max:255',
        ]);

        abort_unless(Transaction::dayIsOpenFor($validated['date'], auth()->user()), 403, Transaction::lockReason($validated['date']));

        CashOpening::updateOrCreate(
            ['date' => Carbon::parse($validated['date'])->toDateString()],
            ['amount' => $validated['amount'], 'note' => $validated['note'] ?? null, 'recorded_by' => auth()->id()]
        );

        return redirect()->route('accounts.cashbook.index', ['date' => $validated['date']])
            ->with('success', 'Cash on hand set — the brought-forward balance counts from this amount now.');
    }

    public function removeOpening(CashOpening $opening)
    {
        abort_unless(Transaction::dayIsOpenFor($opening->date, auth()->user()), 403, Transaction::lockReason($opening->date));

        $date = $opening->date->toDateString();
        $opening->delete();

        return redirect()->route('accounts.cashbook.index', ['date' => $date])
            ->with('success', 'Cash on hand count removed — the brought-forward balance is worked out from earlier entries again.');
    }

    /**
     * Add an আয় (income) or ব্যয় (expense) খাত from the cash book.
     */
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
        ]);

        $validated['name'] = trim($validated['name']);

        $category = TransactionCategory::firstOrCreate($validated, $validated['type'] === 'income' ? ['pl_group' => 'none'] : []);

        return back()->with('success', ($category->wasRecentlyCreated ? 'Head added: ' : 'Head already exists: ') . $category->name);
    }

    public function destroyCategory(TransactionCategory $category)
    {
        if ($category->transactions()->exists()) {
            return back()->with('error', "“{$category->name}” has entries, so it can't be removed.");
        }

        $category->delete();

        return back()->with('success', "Head removed: {$category->name}");
    }

    protected function date(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : Carbon::today();
        } catch (\Throwable) {
            return Carbon::today();
        }
    }
}
