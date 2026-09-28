<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $month = $this->resolveMonth($request);
        $invoices = $this->monthlyInvoicesQuery($request, $month)->get();

        $totalInvoiced = $invoices->sum('amount');
        $totalCollected = $invoices->sum(fn (Invoice $invoice) => $invoice->amountPaid());
        $totalOutstanding = $totalInvoiced - $totalCollected;

        return view('accounts.invoices.index', compact(
            'invoices',
            'month',
            'totalInvoiced',
            'totalCollected',
            'totalOutstanding'
        ));
    }

    /**
     * All invoices for the filtered month (and optional status), rendered
     * as one continuous printable batch — one invoice per printed page.
     */
    public function printBatch(Request $request)
    {
        $month = $this->resolveMonth($request);
        $invoices = $this->monthlyInvoicesQuery($request, $month)->get();

        return view('accounts.invoices.print-batch', compact('invoices', 'month'));
    }

    private function resolveMonth(Request $request): Carbon
    {
        return $request->filled('month')
            ? Carbon::parse($request->month . '-01')
            : Carbon::today()->startOfMonth();
    }

    private function monthlyInvoicesQuery(Request $request, Carbon $month)
    {
        $query = Invoice::with(['customer', 'product', 'items', 'payments.recordedBy'])
            ->whereYear('billing_month', $month->year)
            ->whereMonth('billing_month', $month->month);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $query->orderBy('invoice_number');
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $billingMonth = Carbon::parse($validated['month'] . '-01');

        $customers = Customer::where('status', true)
            ->with(['product', 'bandwidthRates.bandwidthType'])
            ->get();

        $nextNumber = Invoice::count() + 1;
        $created = 0;
        $skipped = 0;

        foreach ($customers as $customer) {
            $invoice = $this->generateInvoiceFor($customer, $billingMonth, $nextNumber);

            if ($invoice) {
                $nextNumber++;
                $created++;
            } else {
                $skipped++;
            }
        }

        return redirect()
            ->route('accounts.invoices.index', ['month' => $billingMonth->format('Y-m')])
            ->with('success', "{$created} invoice(s) generated for {$billingMonth->format('F Y')}. {$skipped} customer(s) skipped (already invoiced this month, or nothing to bill).");
    }

    /**
     * Generates this month's invoice for a single customer, from their
     * detail page — the one-off equivalent of the monthly batch generate().
     */
    public function generateForCustomer(Customer $customer)
    {
        $billingMonth = Carbon::today()->startOfMonth();

        $invoice = $this->generateInvoiceFor($customer, $billingMonth, Invoice::count() + 1);

        if (! $invoice) {
            return back()->with('error', "Could not generate an invoice for {$billingMonth->format('F Y')} — already invoiced this month, or nothing to bill (no package/bandwidth rate set).");
        }

        return back()->with('success', "Invoice {$invoice->invoice_number} generated for {$billingMonth->format('F Y')}.");
    }

    /**
     * Creates $customer's invoice for $billingMonth using $invoiceNumber,
     * or returns null if they're already invoiced that month or there's
     * nothing to bill them for (no package/bandwidth rate on record).
     */
    private function generateInvoiceFor(Customer $customer, Carbon $billingMonth, int $invoiceNumber): ?Invoice
    {
        $exists = Invoice::where('customer_id', $customer->id)
            ->whereYear('billing_month', $billingMonth->year)
            ->whereMonth('billing_month', $billingMonth->month)
            ->exists();

        if ($exists) {
            return null;
        }

        $billing = $this->resolveBilling($customer);

        if (! $billing) {
            return null;
        }

        $invoice = Invoice::create([
            'invoice_number' => 'INV-' . str_pad($invoiceNumber, 6, '0', STR_PAD_LEFT),
            'customer_id' => $customer->id,
            'product_id' => $billing['product_id'],
            'amount' => $billing['amount'],
            'billing_month' => $billingMonth->toDateString(),
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);

        foreach ($billing['items'] as $item) {
            $invoice->items()->create($item);
        }

        return $invoice;
    }

    /**
     * Works out what to bill $customer for one billing period: bandwidth
     * rates (bandwidth_client, corporate_client), a fixed package
     * (mac_client, corporate_client), or — for corporate accounts that
     * carry both — the sum of the two. Returns null when there's nothing
     * to bill them for.
     *
     * @return array{amount: float, product_id: ?int, items: list<array{label: string, amount: float, rate: float, quantity: float}>}|null
     */
    private function resolveBilling(Customer $customer): ?array
    {
        $amount = 0.0;
        $items = [];

        if ($customer->isBandwidthClient()) {
            // Billed per bandwidth type — each line is (rate per Mbps ×
            // Mbps subscribed) for whatever types are on record for them.
            $billableRates = $customer->bandwidthRates->filter(fn ($rate) => $rate->lineTotal() > 0);

            foreach ($billableRates as $rate) {
                $amount += $rate->lineTotal();
                $items[] = [
                    'label' => "{$rate->bandwidthType->name} Bandwidth",
                    'amount' => (float) $rate->lineTotal(),
                    'rate' => (float) $rate->rate,
                    'quantity' => (float) $rate->quantity,
                ];
            }
        }

        $productId = null;

        if ($customer->usesPackage() && $customer->product_id) {
            // The customer's own package_rate overrides the catalog price
            // when set, so a negotiated discount doesn't get lost.
            $packageAmount = (float) ($customer->package_rate ?? $customer->product->price);
            $amount += $packageAmount;
            $productId = $customer->product_id;

            $items[] = [
                'label' => $customer->product->name,
                'amount' => $packageAmount,
                'rate' => $packageAmount,
                'quantity' => 1,
            ];
        }

        if ($amount <= 0) {
            return null;
        }

        return ['amount' => $amount, 'product_id' => $productId, 'items' => $items];
    }

    public function markPaid(Invoice $invoice)
    {
        if ($invoice->isPaid()) {
            return back()->with('error', 'This invoice is already marked paid.');
        }

        $invoice->recordPayment();

        return back()->with('success', "Invoice {$invoice->invoice_number} marked as paid.");
    }

    /**
     * Corrects a payment entry recorded against this invoice (amount, date,
     * or note) — gated behind the separate access-accounts-payments-edit
     * permission so a regular biller can record payments without also
     * being able to alter/erase them after the fact.
     */
    public function updatePayment(Request $request, Invoice $invoice, Transaction $payment)
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $description = "Invoice {$invoice->invoice_number} — {$invoice->customer->name}";

        if ($validated['note'] ?? null) {
            $description .= " ({$validated['note']})";
        }

        $payment->update([
            'amount' => $validated['amount'],
            'transaction_date' => $validated['payment_date'],
            'description' => $description,
        ]);

        $invoice->recalculateStatus();

        return back()->with('success', 'Payment updated.');
    }

    public function destroyPayment(Invoice $invoice, Transaction $payment)
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $payment->delete();

        $invoice->recalculateStatus();

        return back()->with('success', 'Payment removed.');
    }

    public function print(Invoice $invoice)
    {
        $invoice->load(['customer', 'product', 'items', 'payments.recordedBy']);

        $previousDue = $invoice->customer->outstandingDue($invoice->id);
        $previousDueAlreadyAdded = $invoice->items
            ->where('is_adjustment', true)
            ->contains(fn ($item) => str_starts_with($item->label, 'Previous Due'));

        return view('accounts.invoices.print', compact('invoice', 'previousDue', 'previousDueAlreadyAdded'));
    }

    /**
     * Manual adjustment line (e.g. a mid-month bandwidth upgrade/downgrade
     * charge or credit) — there's no automatic proration, so staff record
     * these by hand on the invoice before it's paid.
     */
    public function storeAdjustment(Request $request, Invoice $invoice)
    {
        if ($invoice->isPaid()) {
            return back()->with('error', 'This invoice is already paid — adjustments are locked.');
        }

        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'amount' => 'required|numeric|not_in:0',
            'remark' => 'nullable|string|max:255',
        ]);

        $invoice->items()->create([
            'label' => $validated['label'],
            'amount' => $validated['amount'],
            'is_adjustment' => true,
            'remark' => $validated['remark'] ?? null,
        ]);

        $invoice->increment('amount', $validated['amount']);

        return back()->with('success', 'Adjustment added.');
    }

    public function destroyAdjustment(Invoice $invoice, InvoiceItem $item)
    {
        if ($invoice->isPaid()) {
            return back()->with('error', 'This invoice is already paid — adjustments are locked.');
        }

        if ($item->invoice_id !== $invoice->id || ! $item->is_adjustment) {
            abort(404);
        }

        $invoice->decrement('amount', $item->amount);
        $item->delete();

        return back()->with('success', 'Adjustment removed.');
    }
}
