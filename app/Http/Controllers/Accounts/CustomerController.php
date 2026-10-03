<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\BandwidthType;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::with(['bandwidthRates.bandwidthType', 'settlementRows.settlement:id,month,bkash_percent'])
            ->orderBy('name')
            ->get();
        $bandwidthTypes = BandwidthType::ordered()->get();

        return view('accounts.customers.index', compact('customers', 'bandwidthTypes'));
    }

    public function create()
    {
        return view('accounts.customers.create', [
            'zones' => $this->zones(),
            'bandwidthTypes' => BandwidthType::ordered()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('customers')->where('customer_type', $request->input('customer_type'))],
            'username' => 'nullable|string|max:100|unique:customers,username',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'zone' => 'nullable|exists:zones,name',
            'customer_type' => 'required|in:mac_client,bandwidth_client',
            'kam_name' => 'nullable|string|max:255',
            'kam_phone' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:100',
            'opening_due' => 'nullable|numeric|min:-999999999|max:999999999',
            'rates_from' => 'nullable|date',
            'bandwidth_rates' => 'nullable|array',
            'bandwidth_rates.*.rate' => 'nullable|numeric|min:0',
            'bandwidth_rates.*.quantity' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
        ]);

        $validated['status'] = $request->boolean('status', true);
        $validated['username'] = filled($validated['username'] ?? null) ? trim($validated['username']) : null;
        $bandwidthRates = $validated['bandwidth_rates'] ?? [];
        $ratesFrom = $validated['rates_from'] ?? null;
        unset($validated['bandwidth_rates'], $validated['rates_from']);
        $validated['opening_due'] = $validated['opening_due'] ?? 0;

        $customer = Customer::create($validated);

        $error = $this->syncBandwidthRates($customer, $bandwidthRates, $ratesFrom);

        return redirect()
            ->route('accounts.customers.show', $customer)
            ->with($error ? 'error' : 'success', $error ?? 'Customer added.');
    }

    /** Profile, bandwidth rates and the customer's zone settlement history. */
    public function show(Customer $customer)
    {
        $customer->load(['bandwidthRates.bandwidthType', 'settlementRows.settlement', 'serviceChanges.type', 'serviceChanges.recorder:id,name']);

        // Bandwidth clients' statement: each month's bill and payment, oldest first.
        $statement = collect();
        if ($customer->isBandwidthClient()) {
            $balance = \App\Support\Dec::round($customer->opening_due ?? 0, 2);
            if ((float) $balance != 0) {
                $statement->push(['date' => null, 'text' => 'Opening due', 'bill' => $balance, 'paid' => null, 'balance' => $balance, 'invoice' => null]);
            }
            $events = \App\Models\BandwidthInvoice::with('lines')->where('customer_id', $customer->id)->get()
                ->map(fn ($i) => ['date' => $i->invoice_date, 'sort' => $i->month->toDateString() . ' 0', 'text' => "Bill {$i->month->format('F Y')} — {$i->invoice_no}", 'bill' => $i->totalBill(), 'paid' => null, 'invoice' => $i])
                ->concat(\App\Models\BandwidthPayment::with('invoice')->where('customer_id', $customer->id)->get()
                    ->map(fn ($p) => ['date' => $p->paid_on, 'sort' => $p->paid_on->toDateString() . ' 1' . str_pad((string) $p->id, 8, '0', STR_PAD_LEFT), 'text' => "Payment · {$p->method}" . ($p->note ? " — {$p->note}" : ''), 'bill' => null, 'paid' => $p->amount, 'invoice' => $p->invoice]))
                ->sortBy('sort');
            foreach ($events as $e) {
                $balance = \App\Support\Dec::round(\App\Support\Dec::sub(\App\Support\Dec::add($balance, $e['bill'] ?? 0), $e['paid'] ?? 0), 2);
                $statement->push($e + ['balance' => $balance]);
            }
        }

        return view('accounts.customers.show', [
            'customer' => $customer,
            'rows' => $customer->settlementRows->where('included', true)->values(),
            'statement' => $statement,
            'monthly' => $customer->isBandwidthClient() ? app(\App\Services\BandwidthBilling::class)->monthly($customer) : [],
            'typeOrder' => \App\Models\BandwidthType::ordered()->pluck('name'),
        ]);
    }

    public function edit(Customer $customer)
    {
        return view('accounts.customers.edit', [
            'customer' => $customer,
            'zones' => $this->zones(),
            'bandwidthTypes' => BandwidthType::ordered()->get(),
            'customerRates' => $customer->bandwidthRates->keyBy('bandwidth_type_id'),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('customers')->where('customer_type', $request->input('customer_type'))->ignore($customer->id)],
            'username' => 'nullable|string|max:100|unique:customers,username,' . $customer->id,
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'zone' => 'nullable|exists:zones,name',
            'customer_type' => 'required|in:mac_client,bandwidth_client',
            'kam_name' => 'nullable|string|max:255',
            'kam_phone' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:100',
            'opening_due' => 'nullable|numeric|min:-999999999|max:999999999',
            'rates_from' => 'nullable|date',
            'bandwidth_rates' => 'nullable|array',
            'bandwidth_rates.*.rate' => 'nullable|numeric|min:0',
            'bandwidth_rates.*.quantity' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
        ]);

        $validated['status'] = $request->boolean('status');
        $validated['username'] = filled($validated['username'] ?? null) ? trim($validated['username']) : null;
        $bandwidthRates = $validated['bandwidth_rates'] ?? [];
        $ratesFrom = $validated['rates_from'] ?? null;
        unset($validated['bandwidth_rates'], $validated['rates_from']);
        // Left out of the request (not just blank): keep what was there.
        $validated['opening_due'] = $validated['opening_due'] ?? ($request->exists('opening_due') ? 0 : $customer->opening_due);

        $customer->update($validated);

        $error = $this->syncBandwidthRates($customer, $bandwidthRates, $ratesFrom);

        return redirect()
            ->route('accounts.customers.show', $customer)
            ->with($error ? 'error' : 'success', $error ?? 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        if (strtolower(auth()->user()->role) !== 'admin') {
            abort(403);
        }

        // Keep every customer that has money history attached.
        if ($customer->settlementRows()->exists() || $customer->bandwidthInvoices()->exists() || $customer->bandwidthPayments()->exists()) {
            return back()->with('error', 'This customer has settlements, invoices or payments on record and cannot be deleted. Mark them inactive instead.');
        }

        // Old invoices (from before billing moved to Zone Settlement) are kept
        // in the database and would be deleted with the customer.
        if (DB::table('invoices')->where('customer_id', $customer->id)->exists()) {
            return back()->with('error', 'This customer has old invoices on record and cannot be deleted. Mark them inactive instead.');
        }

        $customer->delete();

        return redirect()
            ->route('accounts.customers.index')
            ->with('success', 'Customer deleted.');
    }

    /**
     * Bandwidth clients' rates and Mbps. Every type whose rate or Mbps
     * differs from what applies now gets a dated history row (from
     * $from, default today), so the month it changed in is billed in parts;
     * the current values are kept in customer_bandwidth_rates.
     *
     * @param  array<int, array{rate?: string|null, quantity?: string|null}>  $bandwidthRates  bandwidth_type_id => [rate, quantity]
     * @return string|null an error to show, if the date falls in a closed month
     */
    private function syncBandwidthRates(Customer $customer, array $bandwidthRates, ?string $from = null): ?string
    {
        if (! $customer->isBandwidthClient()) {
            $customer->bandwidthRates()->delete();

            return null;
        }

        $from = \Illuminate\Support\Carbon::parse($from ?: now())->startOfDay();
        $current = $customer->bandwidthRates()->get()->keyBy('bandwidth_type_id');
        $changes = [];

        foreach (BandwidthType::pluck('id') as $typeId) {
            $entry = $bandwidthRates[$typeId] ?? [];
            $rate = ($entry['rate'] ?? '') === '' ? null : (float) $entry['rate'];
            $mbps = ($entry['quantity'] ?? '') === '' ? null : (float) $entry['quantity'];
            $old = $current[$typeId] ?? null;

            $same = $old
                ? $rate !== null && abs((float) $old->rate - $rate) < 0.00001 && abs((float) $old->quantity - ($mbps ?? 1)) < 0.00001
                : $rate === null;
            if (! $same) {
                $changes[$typeId] = [$rate, $mbps];
            }
        }

        if (! $changes) {
            return null;
        }
        if (\App\Models\ProfitSheet::isMonthClosed($from)) {
            return 'Rates not changed: ' . \App\Models\Transaction::lockReason($from) . ' Pick a date in an open month.';
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $changes, $from) {
            foreach ($changes as $typeId => [$rate, $mbps]) {
                // A second change on the same start date replaces the first.
                \App\Models\BandwidthServiceChange::updateOrCreate(
                    ['customer_id' => $customer->id, 'bandwidth_type_id' => $typeId, 'effective_from' => $from->toDateString()],
                    ['rate' => $rate ?? 0, 'mbps' => $rate === null ? 0 : $mbps, 'note' => $rate === null ? 'Stopped' : null, 'recorded_by' => auth()->id()],
                );

                $customer->bandwidthRates()->where('bandwidth_type_id', $typeId)->delete();
                if ($rate !== null) {
                    $customer->bandwidthRates()->create(['bandwidth_type_id' => $typeId, 'rate' => $rate, 'quantity' => $mbps ?? 1]);
                }
            }
        });

        return null;
    }

    private function zones()
    {
        return \App\Models\Zone::names();
    }
}
