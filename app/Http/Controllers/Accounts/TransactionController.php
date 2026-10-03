<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $start = $request->filled('start')
            ? Carbon::parse($request->start)
            : Carbon::today()->startOfMonth();

        $end = $request->filled('end')
            ? Carbon::parse($request->end)
            : Carbon::today();

        $query = Transaction::with(['category', 'recordedBy'])
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()]);

        if ($request->filled('type')) {
            $query->whereHas('category', fn ($q) => $q->where('type', $request->type));
        }

        if ($request->filled('account')) {
            $query->where('account', $request->account);
        }

        if ($request->filled('category_id')) {
            $query->where('transaction_category_id', $request->category_id);
        }

        $transactions = $query->orderByDesc('transaction_date')->orderByDesc('id')->get();

        $totalIncome = (clone $query)->income()->sum('amount');
        $totalExpense = (clone $query)->expense()->sum('amount');

        $categories = TransactionCategory::orderBy('type')->orderBy('name')->get();

        return view('accounts.transactions.index', compact(
            'transactions',
            'categories',
            'start',
            'end',
            'totalIncome',
            'totalExpense'
        ));
    }

    public function create()
    {
        $categories = TransactionCategory::orderBy('type')->orderBy('name')->get();

        return view('accounts.transactions.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_category_id' => 'required|exists:transaction_categories,id',
            'account' => 'required|in:' . implode(',', array_keys(Transaction::ACCOUNTS)),
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
        ]);

        abort_unless(Transaction::dayIsOpenFor($validated['transaction_date'], auth()->user()), 403, Transaction::lockReason($validated['transaction_date']));
        if ($error = $this->accountError($validated)) {
            return back()->withInput()->withErrors(['account' => $error]);
        }

        $same = Transaction::where('transaction_category_id', $validated['transaction_category_id'])->where('account', $validated['account'])
            ->where('amount', $validated['amount'])->whereDate('transaction_date', $validated['transaction_date'])
            ->where('description', $validated['description'] ?? null);
        if (\App\Support\DuplicateGuard::recent($same)) {
            return back()->withInput()->with('error', \App\Support\DuplicateGuard::message());
        }

        $validated['recorded_by'] = auth()->id();
        $validated['zone'] = auth()->user()->zone;

        Transaction::create($validated);

        return redirect()
            ->route('accounts.transactions.index')
            ->with('success', 'Transaction Recorded Successfully');
    }

    /**
     * Printable "money received" receipt for an income transaction —
     * a payment slip that can be handed to the customer/payer. Expense
     * transactions have nothing to receive, so they don't get a receipt.
     */
    public function print(Transaction $transaction)
    {
        abort_unless($transaction->category->type === 'income', 404);

        $transaction->load(['category', 'recordedBy']);

        return view('accounts.transactions.print', compact('transaction'));
    }

    public function edit(Transaction $transaction)
    {
        abort_if($transaction->isLockedFor(auth()->user()), 403, Transaction::lockReason($transaction->transaction_date));

        $categories = TransactionCategory::orderBy('type')->orderBy('name')->get();

        return view('accounts.transactions.edit', compact('transaction', 'categories'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'transaction_category_id' => 'required|exists:transaction_categories,id',
            'account' => 'required|in:' . implode(',', array_keys(Transaction::ACCOUNTS)),
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
        ]);

        // Neither the old nor the new date may be a closed day.
        abort_if($transaction->isLockedFor(auth()->user()), 403, Transaction::lockReason($transaction->transaction_date));
        abort_unless(Transaction::dayIsOpenFor($validated['transaction_date'], auth()->user()), 403, Transaction::lockReason($validated['transaction_date']));

        if ($error = $this->accountError($validated)) {
            return back()->withInput()->withErrors(['account' => $error]);
        }

        $transaction->update($validated);

        return redirect()
            ->route('accounts.transactions.index')
            ->with('success', 'Transaction Updated Successfully');
    }

    public function destroy(Transaction $transaction)
    {
        if (strtolower(auth()->user()->role) !== 'admin') {
            abort(403);
        }

        $transaction->delete();

        return redirect()
            ->route('accounts.transactions.index')
            ->with('success', 'Transaction Deleted Successfully');
    }

    /** All income is deposited in the bank; petty cash is only for the office's expenses. */
    protected function accountError(array $validated): ?string
    {
        $category = TransactionCategory::find($validated['transaction_category_id']);

        return Transaction::isRealIncome($category) && $validated['account'] === 'cash'
            ? 'Income is deposited in the bank — choose Bank. Petty cash is only for the office\'s expenses.'
            : null;
    }
}
