<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\CustomerProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * MAC client set-up on the customer page:
 *  - allowed packages, each at a fixed rate per user or the list price
 *    less a commission %, with how many users they bill each month;
 *  - products (goods) handed over, which go on the next invoice unless
 *    marked free.
 */
class CustomerPackageController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        $data = $this->validatePackage($request, $customer);

        $customer->packages()->create($data);

        return $this->back($customer, 'Package allowed for this client.');
    }

    public function update(Request $request, Customer $customer, CustomerPackage $package)
    {
        abort_unless($package->customer_id === $customer->id, 404);

        $package->update($this->validatePackage($request, $customer, $package));

        return $this->back($customer, 'Package updated.');
    }

    public function destroy(Customer $customer, CustomerPackage $package)
    {
        abort_unless($package->customer_id === $customer->id, 404);

        $package->delete();

        return $this->back($customer, 'Package removed from this client.');
    }

    public function storeProduct(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'name' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1|max:100000',
            'unit_price' => 'nullable|numeric|min:0',
            'chargeable' => 'nullable|boolean',
            'given_on' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $product = isset($data['product_id']) ? Product::find($data['product_id']) : null;
        $name = trim((string) ($data['name'] ?? '')) ?: $product?->name;

        if (! $name) {
            return back()->withInput()->withErrors(['name' => 'Choose a product or type what was given.']);
        }

        $customer->givenProducts()->create([
            'product_id' => $product?->id,
            'name' => $name,
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'] ?? $product?->price ?? 0,
            'chargeable' => $request->boolean('chargeable'),
            'given_on' => $data['given_on'],
            'note' => $data['note'] ?? null,
        ]);

        return $this->back($customer, 'Product recorded' . ($request->boolean('chargeable') ? ' — it will go on the next invoice.' : ' (free, not billed).'));
    }

    public function destroyProduct(Customer $customer, CustomerProduct $product)
    {
        abort_unless($product->customer_id === $customer->id, 404);

        if ($product->invoice_id) {
            return back()->with('error', "“{$product->name}” is already on invoice {$product->invoice?->invoice_number} and can't be removed.");
        }

        $product->delete();

        return $this->back($customer, 'Product removed.');
    }

    protected function validatePackage(Request $request, Customer $customer, ?CustomerPackage $package = null): array
    {
        $data = $request->validate([
            'product_id' => [
                'required', 'exists:products,id',
                Rule::unique('customer_packages')->where('customer_id', $customer->id)->ignore($package?->id),
            ],
            'pricing' => 'required|in:fixed,commission',
            'rate' => 'nullable|required_if:pricing,fixed|numeric|min:0',
            'commission_percent' => 'nullable|required_if:pricing,commission|numeric|min:0|max:100',
            'quantity' => 'nullable|integer|min:0|max:1000000',
            'is_active' => 'nullable|boolean',
        ], [
            'product_id.unique' => 'This package is already allowed for the client — edit it instead.',
            'rate.required_if' => 'Enter the fixed rate per user.',
            'commission_percent.required_if' => 'Enter the commission %.',
        ]);

        return [
            'product_id' => $data['product_id'],
            'pricing' => $data['pricing'],
            'rate' => $data['pricing'] === 'fixed' ? $data['rate'] : null,
            'commission_percent' => $data['pricing'] === 'commission' ? $data['commission_percent'] : null,
            'quantity' => (int) ($data['quantity'] ?? 0),
            'is_active' => $package ? $request->boolean('is_active') : true,
        ];
    }

    protected function back(Customer $customer, string $message)
    {
        return redirect()->route('accounts.customers.show', $customer)->with('success', $message);
    }
}
