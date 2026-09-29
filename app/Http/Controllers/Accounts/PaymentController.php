<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Receive Payment": take money from a customer in one step. The amount
 * is applied to their unpaid invoices oldest first, one payment
 * (Transaction) per invoice, and a single combined receipt is printed.
 */
class PaymentController extends Controller
{
    public const METHODS = ['Cash', 'bKash', 'Nagad', 'Rocket', 'Bank Transfer', 'Cheque', 'Other'];

    public function create(Request $request)
    {
        $customers = Customer::with(['invoices' => fn ($q) => $q->whereIn('status', ['unpaid', 'partially_paid'])->with('payments')])
            ->orderBy('name')
            ->get()
            ->map(function (Customer $c) {
                $open = $this->openInvoices($c);

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'zone' => $c->zone,
                    'due' => round($open->sum('due'), 2),
                    'invoices' => $open->values(),
                ];
            });

        $selected = (int) $request->query('customer');

        return view('accounts.payments.create', [
            'customers' => $customers,
            'selected' => $customers->firstWhere('id', $selected) ? $selected : null,
            'methods' => self::METHODS,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date|before_or_equal:today',
            'method' => 'required|in:' . implode(',', self::METHODS),
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:150',
        ], [
            'payment_date.before_or_equal' => 'The payment date cannot be in the future.',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        $ids = DB::transaction(function () use ($customer, $validated) {
            // Lock the customer's open invoices so two cashiers can't
            // apply the same due twice.
            $invoices = $customer->invoices()
                ->whereIn('status', ['unpaid', 'partially_paid'])
                ->orderBy('billing_month')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $totalDue = round($invoices->sum(fn (Invoice $i) => $i->remainingDue()), 2);
            $amount = round((float) $validated['amount'], 2);

            if ($totalDue <= 0) {
                throw ValidationException::withMessages(['customer_id' => "{$customer->name} has nothing due."]);
            }

            if ($amount > $totalDue) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount is more than the total due (৳' . number_format($totalDue, 2) . ').',
                ]);
            }

            $reference = trim($validated['reference'] ?? '');
            $extra = trim($validated['note'] ?? '');

            $note = $validated['method']
                . ($reference !== '' ? ' #' . $reference : '')
                . ($extra !== '' ? ' — ' . $extra : '');

            $left = $amount;
            $ids = [];

            foreach ($invoices as $invoice) {
                if ($left <= 0) {
                    break;
                }

                $pay = min($left, round($invoice->remainingDue(), 2));

                if ($pay <= 0) {
                    continue;
                }

                $ids[] = $invoice->recordPayment($pay, $validated['payment_date'], $note)->id;
                $left = round($left - $pay, 2);
            }

            return $ids;
        });

        return redirect()->route('accounts.payments.receipt', ['ids' => implode(',', $ids)]);
    }

    /**
     * One printable receipt for everything taken in a single collection.
     */
    public function receipt(Request $request)
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('ids'))));

        $payments = Transaction::with(['invoice.customer', 'invoice.payments', 'recordedBy'])
            ->whereIn('id', $ids)
            ->whereNotNull('invoice_id')
            ->orderBy('id')
            ->get();

        abort_if($payments->isEmpty(), 404);

        $customer = $payments->first()->invoice->customer;

        // All lines must belong to one customer.
        abort_if($payments->contains(fn ($p) => $p->invoice->customer_id !== $customer->id), 404);

        $remaining = round($customer->outstandingDue(), 2);

        return view('accounts.payments.receipt', compact('payments', 'customer', 'remaining'));
    }

    /**
     * @return \Illuminate\Support\Collection<int, array>
     */
    protected function openInvoices(Customer $customer)
    {
        return $customer->invoices
            ->sortBy([['billing_month', 'asc'], ['id', 'asc']])
            ->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->invoice_number,
                'month' => $i->billing_month?->format('M Y'),
                'amount' => round((float) $i->amount, 2),
                'paid' => round($i->amountPaid(), 2),
                'due' => round($i->remainingDue(), 2),
            ])
            ->filter(fn ($i) => $i['due'] > 0);
    }
}
