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
        $bandwidthTypes = BandwidthType::orderBy('name')->get();

        return view('accounts.customers.index', compact('customers', 'bandwidthTypes'));
    }

    public function create()
    {
        return view('accounts.customers.create', [
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
            'customer_type' => 'required|in:mac_client,bandwidth_client',
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

        return redirect()
            ->route('accounts.customers.show', $customer)
            ->with('success', 'Customer added.');
    }

    /** Profile, bandwidth rates and the customer's zone settlement history. */
    public function show(Customer $customer)
    {
        $customer->load(['bandwidthRates.bandwidthType', 'settlementRows.settlement', 'settlementRows.transaction:id,amount']);

        return view('accounts.customers.show', [
            'customer' => $customer,
            'rows' => $customer->settlementRows->where('included', true)->values(),
        ]);
    }

    public function edit(Customer $customer)
    {
        return view('accounts.customers.edit', [
            'customer' => $customer,
            'zones' => $this->zones(),
            'bandwidthTypes' => BandwidthType::orderBy('name')->get(),
            'customerRates' => $customer->bandwidthRates->keyBy('bandwidth_type_id'),
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
            'customer_type' => 'required|in:mac_client,bandwidth_client',
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
            ->route('accounts.customers.show', $customer)
            ->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        if (strtolower(auth()->user()->role) !== 'admin') {
            abort(403);
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
