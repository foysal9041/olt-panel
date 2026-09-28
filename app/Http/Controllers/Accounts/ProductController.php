<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->orderBy('name')->get();
        $categories = ProductCategory::orderBy('name')->get();

        return view('accounts.products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $categories = ProductCategory::orderBy('name')->get();

        return view('accounts.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,one_time',
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
        ]);

        $validated['status'] = $request->boolean('status', true);

        Product::create($validated);

        return redirect()
            ->route('accounts.products.index')
            ->with('success', 'Product Added Successfully');
    }

    public function edit(Product $product)
    {
        $categories = ProductCategory::orderBy('name')->get();

        return view('accounts.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'product_category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,one_time',
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
        ]);

        $validated['status'] = $request->boolean('status');

        $product->update($validated);

        return redirect()
            ->route('accounts.products.index')
            ->with('success', 'Product Updated Successfully');
    }

    public function destroy(Product $product)
    {
        if ($product->customers()->exists()) {
            return back()->with('error', 'This product has customers subscribed to it and cannot be deleted.');
        }

        $product->delete();

        return redirect()
            ->route('accounts.products.index')
            ->with('success', 'Product Deleted Successfully');
    }
}
