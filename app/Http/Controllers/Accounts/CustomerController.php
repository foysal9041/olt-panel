<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\BandwidthType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::with(['product', 'packages', 'bandwidthRates.bandwidthType', 'invoices.payments'])
            ->orderBy('name')
            ->get()
            ->each(function (Customer $customer) {
                $customer->outstanding_due = $customer->invoices
                    ->whereIn('status', ['unpaid', 'partially_paid'])
                    ->sum(fn (Invoice $invoice) => $invoice->remainingDue());
            });
        $bandwidthTypes = BandwidthType::orderBy('name')->get();

        return view('accounts.customers.index', compact('customers', 'bandwidthTypes'));
    }

    public function create()
    {
        return view('accounts.customers.create', [
            'products' => Product::where('status', true)->orderBy('name')->get(),
            'zones' => $this->zones(),
            'bandwidthTypes' => BandwidthType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'zone' => 'nullable|exists:zones,name',
            'product_id' => 'nullable|exists:products,id',
            'customer_type' => 'required|in:mac_client,bandwidth_client',
            'package_rate' => 'nullable|numeric|min:0',
            'kam_name' => 'nullable|string|max:255',
            'kam_phone' => 'nullable|string|max:50',
            'bandwidth_rates' => 'nullable|array',
            'bandwidth_rates.*.rate' => 'nullable|numeric|min:0',
            'bandwidth_rates.*.quantity' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
        ]);

        $validated['status'] = $request->boolean('status', true);
        $bandwidthRates = $validated['bandwidth_rates'] ?? [];
        unset($validated['bandwidth_rates']);

        $customer = Customer::create($validated);

        $this->syncBandwidthRates($customer, $bandwidthRates);

        // MAC clients are set up on their page (packages, products given).
        if ($customer->usesPackage()) {
            return redirect()
                ->route('accounts.customers.show', $customer)
                ->with('success', 'Customer added — now allow their packages below.');
        }

        return redirect()
            ->route('accounts.customers.index')
            ->with('success', 'Customer Added Successfully');
    }

    public function show(Customer $customer)
    {
        $customer->load(['product', 'bandwidthRates.bandwidthType', 'packages', 'givenProducts.invoice']);
        $invoices = $customer->invoices()->with('payments')->orderByDesc('billing_month')->get();

        // MAC clients: packages are monthly products; goods are one-time ones.
        $catalog = \App\Models\Product::with('category')->where('status', true)->orderBy('name')->get();
        $packageOptions = $catalog->where('billing_cycle', 'monthly')->values();
        $goodsOptions = $catalog->where('billing_cycle', '!=', 'monthly')->values();

        // Next month to bill: the month after the latest invoice, else this month.
        $lastBilled = $invoices->first()?->billing_month;
        $nextMonth = $lastBilled ? $lastBilled->copy()->startOfMonth()->addMonthNoOverflow() : now()->startOfMonth();

        return view('accounts.customers.show', compact('customer', 'invoices', 'packageOptions', 'goodsOptions', 'nextMonth'));
    }

    /**
     * Records a payment against one of this customer's outstanding
     * invoices (the "Money Received" form on the customer detail page) and
     * sends the staff member straight to the printable receipt. The amount
     * doesn't have to cover the whole invoice — a customer can pay one
     * invoice off across several visits, and the invoice tracks how much
     * is still due until it's fully settled.
     */
    public function recordPayment(Request $request, Customer $customer)
    {
        $invoice = $customer->invoices()
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->findOrFail($request->input('invoice_id'));

        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . $invoice->remainingDue()],
            'payment_date' => 'nullable|date',
            'note' => 'nullable|string|max:255',
        ]);

        $transaction = $invoice->recordPayment(
            (float) $validated['amount'],
            $validated['payment_date'] ?? null,
            $validated['note'] ?? null
        );

        return redirect()->route('accounts.transactions.print', $transaction->id);
    }

    /**
     * The in-app "Ledger" page — same statement as printLedger() below, but
     * rendered inside the normal site layout (sidebar/header, no print
     * chrome) so staff can browse it without popping a new tab. Has its own
     * "Print" button through to the printable version.
     */
    public function ledger(Customer $customer)
    {
        return view('accounts.customers.ledger', $this->buildLedger($customer));
    }

    /**
     * A printable statement of every invoice issued to this customer and
     * every payment received against them, oldest first, with a running
     * balance. Only invoice-linked payments are attributable to a
     * customer, so that's the whole of the ledger (there's no separate
     * customer-tagged transaction to include).
     */
    public function printLedger(Customer $customer)
    {
        return view('accounts.customers.ledger-print', $this->buildLedger($customer));
    }

    /**
     * @return array{customer: Customer, entries: \Illuminate\Support\Collection, totalDebit: float, totalCredit: float, closingBalance: float}
     */
    private function buildLedger(Customer $customer): array
    {
        $invoices = $customer->invoices()->with('payments')->orderBy('billing_month')->orderBy('id')->get();

        $entries = $invoices
            ->flatMap(function (Invoice $invoice) {
                $rows = collect([[
                    'date' => $invoice->issued_at ?? $invoice->billing_month,
                    'description' => "Invoice {$invoice->invoice_number} — {$invoice->billing_month->format('F Y')}",
                    'debit' => (float) $invoice->amount,
                    'credit' => 0.0,
                ]]);

                foreach ($invoice->payments as $payment) {
                    $rows->push([
                        'date' => $payment->transaction_date,
                        'description' => "Payment received — Invoice {$invoice->invoice_number}",
                        'debit' => 0.0,
                        'credit' => (float) $payment->amount,
                    ]);
                }

                return $rows;
            })
            ->sortBy('date')
            ->values();

        $balance = 0.0;

        $entries = $entries->map(function ($entry) use (&$balance) {
            $balance += $entry['debit'] - $entry['credit'];
            $entry['balance'] = $balance;

            return $entry;
        });

        return [
            'customer' => $customer,
            'entries' => $entries,
            'totalDebit' => $entries->sum('debit'),
            'totalCredit' => $entries->sum('credit'),
            'closingBalance' => $balance,
        ];
    }

    public function edit(Customer $customer)
    {
        return view('accounts.customers.edit', [
            'customer' => $customer,
            'products' => Product::where('status', true)->orderBy('name')->get(),
            'zones' => $this->zones(),
            'bandwidthTypes' => BandwidthType::orderBy('name')->get(),
            'customerRates' => $customer->bandwidthRates->keyBy('bandwidth_type_id'),
            'invoices' => $customer->invoices()->orderByDesc('billing_month')->get(),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'zone' => 'nullable|exists:zones,name',
            'product_id' => 'nullable|exists:products,id',
            'customer_type' => 'required|in:mac_client,bandwidth_client',
            'package_rate' => 'nullable|numeric|min:0',
            'kam_name' => 'nullable|string|max:255',
            'kam_phone' => 'nullable|string|max:50',
            'bandwidth_rates' => 'nullable|array',
            'bandwidth_rates.*.rate' => 'nullable|numeric|min:0',
            'bandwidth_rates.*.quantity' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
        ]);

        $validated['status'] = $request->boolean('status');
        $bandwidthRates = $validated['bandwidth_rates'] ?? [];
        unset($validated['bandwidth_rates']);

        $customer->update($validated);

        $this->syncBandwidthRates($customer, $bandwidthRates);

        return redirect()
            ->route('accounts.customers.index')
            ->with('success', 'Customer Updated Successfully');
    }

    public function destroy(Customer $customer)
    {
        if (strtolower(auth()->user()->role) !== 'admin') {
            abort(403);
        }

        if ($customer->invoices()->exists()) {
            return back()->with('error', 'This customer has invoices on record and cannot be deleted. Mark them inactive instead.');
        }

        $customer->delete();

        return redirect()
            ->route('accounts.customers.index')
            ->with('success', 'Customer Deleted Successfully');
    }

    /**
     * @param  array<int, array{rate?: string|null, quantity?: string|null}>  $bandwidthRates  bandwidth_type_id => [rate, quantity]
     */
    private function syncBandwidthRates(Customer $customer, array $bandwidthRates): void
    {
        $customer->bandwidthRates()->delete();

        if (! $customer->isBandwidthClient()) {
            return;
        }

        $validTypeIds = BandwidthType::pluck('id')->all();

        foreach ($bandwidthRates as $typeId => $entry) {
            $rate = $entry['rate'] ?? null;

            if ($rate === null || $rate === '' || ! in_array((int) $typeId, $validTypeIds, true)) {
                continue;
            }

            $quantity = $entry['quantity'] ?? null;

            $customer->bandwidthRates()->create([
                'bandwidth_type_id' => (int) $typeId,
                'rate' => $rate,
                'quantity' => ($quantity === null || $quantity === '') ? 1 : $quantity,
            ]);
        }
    }

    private function zones()
    {
        return \App\Models\Zone::names();
    }
}
