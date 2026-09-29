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

        $entries = Transaction::with('category', 'invoice.customer')
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

        $categories = TransactionCategory::orderBy('name')->get()->groupBy('type');

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
            'transaction_category_id.required' => 'Choose a খাত (category).',
        ]);

        abort_unless(Transaction::dayIsOpenFor($validated['transaction_date'], auth()->user()), 403, 'Only an admin can change entries for past days.');

        Transaction::create($validated + [
            'recorded_by' => auth()->id(),
            'zone' => auth()->user()->zone,
        ]);

        return redirect()
            ->route('accounts.cashbook.index', ['date' => Carbon::parse($validated['transaction_date'])->toDateString()])
            ->with('success', 'Entry added.');
    }

    public function destroy(Transaction $transaction)
    {
        abort_if($transaction->isLockedFor(auth()->user()), 403, 'Only an admin can change entries for past days.');

        // Invoice payments belong to their invoice; remove them from there.
        if ($transaction->invoice_id) {
            return back()->with('error', 'This is an invoice payment — edit or remove it from the invoice.');
        }

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

        abort_unless(Transaction::dayIsOpenFor($validated['date'], auth()->user()), 403, 'Only an admin can change entries for past days.');

        CashOpening::updateOrCreate(
            ['date' => Carbon::parse($validated['date'])->toDateString()],
            ['amount' => $validated['amount'], 'note' => $validated['note'] ?? null, 'recorded_by' => auth()->id()]
        );

        return redirect()->route('accounts.cashbook.index', ['date' => $validated['date']])
            ->with('success', 'Cash on hand set — জের counts from this amount now.');
    }

    public function removeOpening(CashOpening $opening)
    {
        abort_unless(Transaction::dayIsOpenFor($opening->date, auth()->user()), 403, 'Only an admin can change entries for past days.');

        $date = $opening->date->toDateString();
        $opening->delete();

        return redirect()->route('accounts.cashbook.index', ['date' => $date])
            ->with('success', 'Cash on hand count removed — জের is worked out from earlier entries again.');
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

        $category = TransactionCategory::firstOrCreate($validated);

        return back()->with('success', ($category->wasRecentlyCreated ? 'খাত added: ' : 'খাত already exists: ') . $category->name);
    }

    public function destroyCategory(TransactionCategory $category)
    {
        if ($category->transactions()->exists()) {
            return back()->with('error', "“{$category->name}” has entries, so it can't be removed.");
        }

        $category->delete();

        return back()->with('success', "খাত removed: {$category->name}");
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
