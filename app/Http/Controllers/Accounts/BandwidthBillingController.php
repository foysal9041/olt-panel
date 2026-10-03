<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BandwidthInvoice;
use App\Models\BandwidthPayment;
use App\Models\Customer;
use App\Models\ProfitSheet;
use App\Models\Transaction;
use App\Services\BandwidthBilling;
use App\Support\Dec;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bandwidth clients' monthly invoices (made on the 1st) and the payments
 * against them — several per month, part can stay due and is carried into
 * the next month's invoice.
 */
class BandwidthBillingController extends Controller
{
    public function __construct(private BandwidthBilling $billing)
    {
    }

    public function index(Request $request)
    {
        $month = $this->month($request->query('month'));

        $invoices = BandwidthInvoice::with(['customer', 'lines', 'payments'])->whereDate('month', $month)->orderBy('invoice_no')->get()
            ->each(fn ($i) => $i->figures = $i->figures());

        $missing = Customer::where('customer_type', 'bandwidth_client')
            ->whereNotIn('id', $invoices->pluck('customer_id'))->orderByDesc('status')->orderBy('name')->get();

        $collected = (string) BandwidthPayment::whereBetween('paid_on', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->sum('amount');
        $outstanding = Customer::where('customer_type', 'bandwidth_client')->get()
            ->reduce(fn ($t, $c) => Dec::add($t, $c->bandwidthBalance()), '0');

        return view('accounts.billing.index', [
            'month' => $month,
            'invoices' => $invoices,
            'missing' => $missing,
            'billed' => $invoices->reduce(fn ($t, $i) => Dec::add($t, $i->figures['bill']), '0'),
            'collected' => $collected,
            'outstanding' => $outstanding,
            'closed' => ProfitSheet::isMonthClosed($month),
        ]);
    }

    /**
     * Make invoices by hand: for one client (customer_id), or for every
     * active bandwidth client that has none for the month yet.
     */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'customer_id' => 'nullable|exists:customers,id',
            'invoice_date' => 'nullable|date',
        ]);
        $month = $this->month($data['month']);
        if ($error = $this->closedError($month)) {
            return back()->with('error', $error);
        }
        $date = isset($data['invoice_date']) ? Carbon::parse($data['invoice_date']) : null;

        $clients = Customer::where('customer_type', 'bandwidth_client')
            ->when($data['customer_id'] ?? null, fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->where('status', true))
            ->whereDoesntHave('bandwidthInvoices', fn ($q) => $q->whereDate('month', $month))
            ->get();

        if (($data['customer_id'] ?? null) && $clients->isEmpty()) {
            $existing = BandwidthInvoice::where('customer_id', $data['customer_id'])->whereDate('month', $month)->first();

            return $existing
                ? redirect()->route('accounts.billing.show', $existing)->with('error', "This client already has the {$month->format('F Y')} invoice.")
                : back()->with('error', 'That customer is not a bandwidth client.');
        }

        $made = $clients->map(fn (Customer $c) => $this->billing->generate($c, $month, $request->user(), $date));

        if ($made->count() === 1) {
            return redirect()->route('accounts.billing.show', $made->first())
                ->with('success', "Invoice {$made->first()->invoice_no} made — check the lines, then print or download.");
        }

        return back()->with('success', $made->isEmpty()
            ? "Every active client already has a {$month->format('F Y')} invoice."
            : "Made {$made->count()} " . str('invoice')->plural($made->count()) . " for {$month->format('F Y')}: " . $made->pluck('invoice_no')->implode(', '));
    }

    public function show(BandwidthInvoice $invoice)
    {
        $invoice->load(['customer', 'lines', 'payments.recorder:id,name']);

        return view('accounts.billing.show', [
            'invoice' => $invoice,
            'figures' => $invoice->figures(),
            'closed' => $invoice->isClosed(),
            'types' => \App\Models\BandwidthType::ordered()->get(),
        ]);
    }

    /** Save the lines and header as edited on the invoice page. */
    public function update(Request $request, BandwidthInvoice $invoice)
    {
        if ($error = $this->closedError($invoice->month)) {
            return back()->with('error', $error);
        }

        $data = $request->validate([
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'prepared_by' => 'nullable|string|max:100',
            'prepared_title' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'lines' => 'array',
            'lines.*.label' => 'nullable|string|max:100',
            'lines.*.bandwidth_type_id' => 'nullable|exists:bandwidth_types,id',
            'lines.*.period_from' => 'nullable|date',
            'lines.*.period_to' => 'nullable|date',
            'lines.*.rate' => 'nullable|numeric|min:0',
            'lines.*.mbps' => 'nullable|numeric|min:0',
            'lines.*.amount' => 'nullable|numeric|min:-999999999|max:999999999',
            'lines.*.remark' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($invoice, $data) {
            $invoice->update(collect($data)->only(['invoice_date', 'due_date', 'prepared_by', 'prepared_title', 'notes'])->all());
            $invoice->lines()->delete();
            foreach (array_values(array_filter($data['lines'] ?? [], fn ($l) => filled($l['label'] ?? null))) as $i => $l) {
                $invoice->lines()->create([
                    'bandwidth_type_id' => $l['bandwidth_type_id'] ?? null,
                    'label' => trim($l['label']),
                    'period_from' => $l['period_from'] ?? null,
                    'period_to' => $l['period_to'] ?? null,
                    'rate' => $l['rate'] ?? null,
                    'mbps' => $l['mbps'] ?? null,
                    'amount' => Dec::round($l['amount'] ?? 0, 4),
                    'remark' => $l['remark'] ?? null,
                    'sort' => $i,
                ]);
            }
        });

        return back()->with('success', "{$invoice->invoice_no} saved — Total Bill ৳" . number_format((float) $invoice->fresh('lines')->totalBill(), 2) . '.');
    }

    /** Throw away the lines and work them out again from the service history. */
    public function recalculate(BandwidthInvoice $invoice)
    {
        if ($error = $this->closedError($invoice->month)) {
            return back()->with('error', $error);
        }

        $this->billing->recalculate($invoice);

        return back()->with('success', 'Lines worked out again from the client\'s rates and Mbps.');
    }

    public function destroy(Request $request, BandwidthInvoice $invoice)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only an admin can delete an invoice.');
        if ($error = $this->closedError($invoice->month)) {
            return back()->with('error', $error);
        }
        if ($invoice->payments()->exists()) {
            return back()->with('error', 'This invoice has payments — remove them first.');
        }

        $month = $invoice->month->format('Y-m');
        $invoice->delete();

        return redirect()->route('accounts.billing.index', ['month' => $month])->with('success', "Invoice {$invoice->invoice_no} deleted.");
    }

    public function print(BandwidthInvoice $invoice)
    {
        $invoice->load(['customer', 'lines', 'payments']);

        return view('accounts.billing.print', ['invoices' => collect([$invoice]), 'month' => $invoice->month]);
    }

    public function printMonth(Request $request)
    {
        $month = $this->month($request->query('month'));
        $invoices = BandwidthInvoice::with(['customer', 'lines', 'payments'])->whereDate('month', $month)->orderBy('invoice_no')->get();
        abort_if($invoices->isEmpty(), 404);

        return view('accounts.billing.print', ['invoices' => $invoices, 'month' => $month]);
    }

    /**
     * Money received, against the invoice it was taken on. All income is
     * deposited in the bank — even cash — so it never touches the petty
     * cash (Cash Book); the Net Profit sheet takes it from here.
     */
    public function storePayment(Request $request, BandwidthInvoice $invoice)
    {
        $data = $request->validate([
            'paid_on' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0.01|max:999999999',
            'method' => 'required|in:' . implode(',', BandwidthPayment::METHODS),
            'received_by' => 'nullable|string|max:100',
            'reference' => ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('bandwidth_payments', 'reference')->where('customer_id', $invoice->customer_id)],
            'note' => 'nullable|string|max:255',
        ], ['reference.unique' => 'A payment with this reference is already recorded for this client.']);
        $data['received_by'] = filled($data['received_by'] ?? null) ? trim($data['received_by']) : $request->user()->name;

        if ($error = $this->closedError(Carbon::parse($data['paid_on']))) {
            return back()->withInput()->with('error', $error);
        }

        $amount = Dec::round($data['amount'], 2);
        $customer = $invoice->customer;

        $same = BandwidthPayment::where('customer_id', $customer->id)->whereDate('paid_on', $data['paid_on'])->where('amount', $amount)->where('method', $data['method']);
        if (\App\Support\DuplicateGuard::recent($same)) {
            return back()->withInput()->with('error', \App\Support\DuplicateGuard::message());
        }

        $payment = BandwidthPayment::create([
            'customer_id' => $customer->id,
            'bandwidth_invoice_id' => $invoice->id,
            'paid_on' => $data['paid_on'],
            'amount' => $amount,
            'method' => $data['method'],
            'received_by' => $data['received_by'],
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        $due = $invoice->fresh(['customer', 'lines', 'payments'])->figures()['due'];

        return back()
            ->with('success', "Received ৳" . number_format((float) $amount, 2) . " from {$customer->name} — receipt {$payment->receiptNo()}. Due now ৳" . number_format((float) $due, 2) . '.')
            ->with('receipt', $payment->id);
    }

    /** Money receipt for one payment, on the pad. */
    public function receipt(BandwidthInvoice $invoice, BandwidthPayment $payment)
    {
        abort_unless($payment->bandwidth_invoice_id === $invoice->id, 404);
        $invoice->load(['customer', 'lines', 'payments']);

        // What was still due right after this payment.
        $after = $invoice->figures()['mrc'];
        foreach ($invoice->payments as $p) {
            $after = Dec::sub($after, $p->amount);
            if ($p->id === $payment->id) {
                break;
            }
        }

        return view('accounts.billing.receipt', ['invoice' => $invoice, 'payment' => $payment, 'dueAfter' => Dec::round($after, 2)]);
    }

    public function destroyPayment(Request $request, BandwidthInvoice $invoice, BandwidthPayment $payment)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only an admin can remove a payment.');
        abort_unless($payment->bandwidth_invoice_id === $invoice->id, 404);

        if ($error = $this->closedError($payment->paid_on)) {
            return back()->with('error', $error);
        }
        if ($payment->transaction && ! Transaction::dayIsOpenFor($payment->transaction->transaction_date, $request->user())) {
            return back()->with('error', Transaction::lockReason($payment->transaction->transaction_date));
        }

        DB::transaction(function () use ($payment) {
            $payment->transaction?->delete();
            $payment->delete();
        });
        ActivityLog::record('deleted', "Removed payment ৳" . number_format((float) $payment->amount, 2) . " ({$payment->paid_on->format('d M Y')}) from {$invoice->invoice_no}", $invoice);

        return back()->with('success', 'Payment removed.');
    }

    protected function closedError(\DateTimeInterface $month): ?string
    {
        return ProfitSheet::isMonthClosed($month) ? Transaction::lockReason($month) : null;
    }

    /** ?month=Y-m, else this month (invoices are made on the 1st). */
    protected function month(?string $value): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : Carbon::today()->startOfMonth();
        } catch (\Throwable) {
            return Carbon::today()->startOfMonth();
        }
    }
}
