<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\TransactionCategory;
use Illuminate\Http\Request;

class TransactionCategoryController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
        ]);

        TransactionCategory::create($validated);

        return back()->with('success', 'Category Added Successfully');
    }

    public function destroy(TransactionCategory $category)
    {
        if ($category->transactions()->exists()) {
            return back()->with('error', 'This category is used by existing transactions and cannot be deleted.');
        }

        $category->delete();

        return back()->with('success', 'Category Removed Successfully');
    }
}
