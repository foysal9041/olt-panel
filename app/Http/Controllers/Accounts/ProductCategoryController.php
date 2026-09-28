<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:product_categories,name',
        ]);

        ProductCategory::create($validated);

        return back()->with('success', 'Category Added Successfully');
    }

    public function destroy(ProductCategory $category)
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'This category has products in it and cannot be deleted.');
        }

        $category->delete();

        return back()->with('success', 'Category Removed Successfully');
    }
}
