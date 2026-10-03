<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Services\InventoryStock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Products: what the company buys, keeps, uses and sells. */
class ItemController extends Controller
{
    public function __construct(private InventoryStock $stock)
    {
    }

    public function index(Request $request)
    {
        $items = InventoryItem::with('category')
            ->when($request->filled('category'), fn ($q) => $q->where('inventory_category_id', $request->category))
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->kind))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$request->q}%")
                ->orWhere('brand', 'like', "%{$request->q}%")->orWhere('model', 'like', "%{$request->q}%")))
            ->orderByDesc('is_active')->orderBy('name')->get();

        $figures = $this->stock->figures();
        $blank = $this->stock->blank();
        $rows = $items->map(fn ($i) => ['item' => $i, 'f' => $figures->get($i->id) ?? $blank]);
        if ($request->query('stock') === 'low') {
            $rows = $rows->filter(fn ($r) => (float) $r['item']->min_stock > 0 && $r['f']->on_hand <= (float) $r['item']->min_stock)->values();
        }

        return view('inventory.items.index', [
            'rows' => $rows,
            'categories' => InventoryCategory::withCount('items')->orderBy('name')->get(),
            'summary' => $this->stock->summary(),
        ]);
    }

    public function show(InventoryItem $item)
    {
        $item->load('category');
        $movements = $item->movements()->with(['customer', 'recordedBy', 'transaction'])->orderBy('date')->orderBy('id')->get();

        // Running balance in the store, oldest first.
        $balance = 0.0;
        foreach ($movements as $m) {
            $fromStore = ! ($m->type === 'damage' && $m->location);
            if ($fromStore) {
                $balance += $m->isIn() ? (float) $m->quantity : -(float) $m->quantity;
            }
            $m->store_balance = round($balance, 2);
        }

        return view('inventory.items.show', [
            'item' => $item,
            'f' => $this->stock->forItem($item),
            'movements' => $movements->reverse()->values(),
            'locations' => $this->stock->atLocations($item->id)->sortByDesc('qty')->values(),
        ]);
    }

    public function store(Request $request)
    {
        $item = InventoryItem::create($this->validated($request));

        return redirect()->route('inventory.items.show', $item)
            ->with('success', "{$item->name} added. Now enter its opening stock or a purchase.");
    }

    public function update(Request $request, InventoryItem $item)
    {
        $item->update($this->validated($request, $item));

        return back()->with('success', "{$item->name} saved.");
    }

    public function destroy(InventoryItem $item)
    {
        if ($item->movements()->exists()) {
            return back()->with('error', "{$item->name} has stock entries, so it can't be removed — mark it inactive instead.");
        }
        $item->delete();

        return redirect()->route('inventory.items.index')->with('success', "{$item->name} removed.");
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        $category = InventoryCategory::firstOrCreate(['name' => trim($data['name'])]);

        return back()->with('success', ($category->wasRecentlyCreated ? 'Category added: ' : 'Category already exists: ') . $category->name);
    }

    public function destroyCategory(InventoryCategory $category)
    {
        if ($category->items()->exists()) {
            return back()->with('error', "“{$category->name}” has products, so it can't be removed.");
        }
        $category->delete();

        return back()->with('success', "Category removed: {$category->name}");
    }

    protected function validated(Request $request, ?InventoryItem $item = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('inventory_items')->ignore($item?->id)
                ->where(fn ($q) => $q->where('brand', $request->input('brand'))->where('model', $request->input('model')))],
            'inventory_category_id' => 'nullable|exists:inventory_categories,id',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'unit' => 'required|string|max:20',
            'kind' => ['required', Rule::in(array_keys(InventoryItem::KINDS))],
            'min_stock' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ], ['name.unique' => 'This product (same name, brand and model) is already in the list.']);

        $data['min_stock'] = $data['min_stock'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', $item?->is_active ?? true);
        $data['name'] = trim($data['name']);

        return $data;
    }
}
