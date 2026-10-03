<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\Zone;
use App\Services\InventoryStock;
use App\Support\DuplicateGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Stock entries: opening stock, purchases, use at a location, returns,
 * sales, damage and stock counts. Entries are not edited — a wrong one is
 * removed and entered again, so the history stays honest.
 */
class MovementController extends Controller
{
    public function __construct(private InventoryStock $stock)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['type', 'item', 'location', 'from', 'to', 'q']);

        $query = InventoryMovement::with(['item', 'customer', 'recordedBy'])
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['item'] ?? null, fn ($q, $v) => $q->where('inventory_item_id', $v))
            ->when($filters['location'] ?? null, fn ($q, $v) => $q->where('location', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('party', 'like', "%{$v}%")
                ->orWhere('reference', 'like', "%{$v}%")->orWhere('serials', 'like', "%{$v}%")->orWhere('note', 'like', "%{$v}%")
                ->orWhere('location', 'like', "%{$v}%")->orWhereHas('item', fn ($i) => $i->where('name', 'like', "%{$v}%")->orWhere('model', 'like', "%{$v}%"))));

        $totals = (clone $query)->reorder()->groupBy('type')
            ->selectRaw('type, COUNT(*) as n, SUM(quantity * CASE WHEN type = "sale" THEN unit_price ELSE unit_cost END) as value')
            ->get()->keyBy('type');

        return view('inventory.entries.index', [
            'entries' => $query->latest('date')->latest('id')->paginate(50)->withQueryString(),
            'totals' => $totals,
            'filters' => $filters,
            'items' => InventoryItem::orderBy('name')->get(['id', 'name', 'brand', 'model']),
            'locations' => $this->locations(),
        ]);
    }

    public function create(Request $request)
    {
        $type = array_key_exists($request->query('type'), InventoryMovement::TYPES) ? $request->query('type') : 'purchase';
        $items = InventoryItem::with('category')->where('is_active', true)->orderBy('name')->get();
        $figures = $this->stock->figures();

        return view('inventory.entries.create', [
            'type' => $type,
            'items' => $items,
            'figures' => $figures,
            'selected' => $request->integer('item') ?: null,
            'locations' => $this->locations(),
            'deployed' => $this->stock->atLocations(),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'customer_type']),
            'heads' => TransactionCategory::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $types = array_keys(InventoryMovement::TYPES);
        $data = $request->validate([
            'type' => ['required', Rule::in($types)],
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'quantity' => 'required|numeric|min:0.01|max:9999999',
            'date' => 'required|date|before_or_equal:' . now()->addDay()->toDateString(),
            'unit_cost' => 'nullable|numeric|min:0|max:999999999',
            'unit_price' => 'nullable|numeric|min:0|max:999999999',
            'party' => 'nullable|string|max:255',
            'customer_id' => 'nullable|exists:customers,id',
            'location' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'serials' => 'nullable|string|max:5000',
            'note' => 'nullable|string|max:1000',
            'to_accounts' => 'nullable|boolean',
            'account' => ['nullable', Rule::in(array_keys(Transaction::ACCOUNTS))],
            'transaction_category_id' => 'nullable|exists:transaction_categories,id',
        ]);
        $type = $data['type'];
        $data['location'] = filled($data['location'] ?? null) ? preg_replace('/\s+/u', ' ', trim($data['location'])) : null;

        // What each type needs.
        $need = [];
        if (in_array($type, InventoryMovement::COSTED, true) && ! filled($data['unit_cost'] ?? null)) {
            $need['unit_cost'] = 'Enter the cost of one unit.';
        }
        if ($type === 'sale' && ! filled($data['unit_price'] ?? null)) {
            $need['unit_price'] = 'Enter the sale price of one unit.';
        }
        if (in_array($type, ['use', 'return'], true) && ! $data['location']) {
            $need['location'] = $type === 'use' ? 'Where was it used? Enter the zone / POP / customer.' : 'Where is it coming back from?';
        }
        if ($type === 'sale' && ! filled($data['party'] ?? null) && ! filled($data['customer_id'] ?? null)) {
            $need['party'] = 'Who bought it? Pick a customer or type the buyer\'s name.';
        }
        if ($need) {
            throw ValidationException::withMessages($need);
        }
        if (! in_array($type, ['use', 'return', 'damage'], true)) {
            $data['location'] = null;
        }
        // One spelling per place: "navaron pop" is the "Navaron POP" already used.
        if ($data['location']) {
            $data['location'] = $this->locations()->first(fn ($l) => mb_strtolower($l) === mb_strtolower($data['location'])) ?? $data['location'];
        }

        // Optional: the matching Income & Expenses entry.
        $toAccounts = $request->boolean('to_accounts') && in_array($type, ['purchase', 'sale'], true);
        $head = null;
        if ($toAccounts) {
            $head = TransactionCategory::find($data['transaction_category_id'] ?? null);
            $wantType = $type === 'purchase' ? 'expense' : 'income';
            if (! $head || $head->type !== $wantType) {
                throw ValidationException::withMessages(['transaction_category_id' => $type === 'purchase' ? 'Pick the expense head for the purchase.' : 'Pick the income head for the sale.']);
            }
            if ($type === 'sale') {
                $data['account'] = 'bank'; // income always goes to the bank
            }
            if (! Transaction::dayIsOpenFor($data['date'], $request->user())) {
                throw ValidationException::withMessages(['date' => Transaction::lockReason($data['date'])]);
            }
        }

        $same = InventoryMovement::where('inventory_item_id', $data['inventory_item_id'])->where('type', $type)
            ->where('quantity', $data['quantity'])->whereDate('date', $data['date'])
            ->where('location', $data['location'])->where('note', $data['note'] ?? null);
        if (DuplicateGuard::recent($same)) {
            return back()->withInput()->with('error', DuplicateGuard::message());
        }

        $movement = DB::transaction(function () use ($data, $type, $toAccounts, $head, $request) {
            $item = InventoryItem::lockForUpdate()->findOrFail($data['inventory_item_id']);

            // Cost of what leaves: the average cost, or what it cost at that location.
            $fromLocation = in_array($type, ['return', 'damage'], true) && $data['location'];
            if (! in_array($type, InventoryMovement::COSTED, true)) {
                $data['unit_cost'] = $fromLocation
                    ? $this->stock->atLocation($item->id, $data['location'])['unit_cost']
                    : $this->stock->forItem($item)->avg_cost;
            }
            if ($fromLocation) {
                // Keep the spelling it was used with, so it adds up there.
                $data['location'] = $this->stock->atLocation($item->id, $data['location'])['location'];
            }

            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'type' => $type,
                'quantity' => $data['quantity'],
                'unit_cost' => $data['unit_cost'] ?? 0,
                'unit_price' => $type === 'sale' ? $data['unit_price'] : null,
                'date' => $data['date'],
                'party' => $data['party'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'location' => $data['location'],
                'reference' => $data['reference'] ?? null,
                'serials' => $data['serials'] ?? null,
                'note' => $data['note'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            if ($problem = $this->stock->problem($item)) {
                throw ValidationException::withMessages(['quantity' => $problem]);
            }

            if ($toAccounts) {
                $amount = round((float) $data['quantity'] * (float) ($type === 'sale' ? $data['unit_price'] : $data['unit_cost']), 2);
                $who = $movement->customer?->name ?? $movement->party;
                $transaction = Transaction::create([
                    'transaction_category_id' => $head->id,
                    'account' => $data['account'] ?? 'bank',
                    'amount' => $amount,
                    'description' => ($type === 'sale' ? 'Sold ' : 'Bought ') . InventoryStock::qty($data['quantity']) . " {$item->unit} {$item->name}" . ($who ? " — {$who}" : ''),
                    'transaction_date' => $data['date'],
                    'recorded_by' => $request->user()->id,
                    'zone' => $request->user()->zone,
                ]);
                $movement->update(['transaction_id' => $transaction->id]);
            }

            return $movement;
        });

        $msg = "Saved — {$movement->typeLabel()}: " . InventoryStock::qty($movement->quantity) . " {$movement->item->unit} {$movement->item->name}"
            . ($movement->transaction_id ? ' (also in Income & Expenses)' : '') . '. In store now: ' . InventoryStock::qty($this->stock->forItem($movement->item)->on_hand) . " {$movement->item->unit}.";

        return redirect()->to($request->input('back') ?: route('inventory.entries.index'))->with('success', $msg);
    }

    public function destroy(Request $request, InventoryMovement $entry)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || ($entry->recorded_by === $user->id && $entry->created_at?->isToday()), 403,
            'Only an admin can remove an entry, or the one who made it on the same day.');

        if ($entry->transaction && \App\Models\ProfitSheet::isMonthClosed($entry->transaction->transaction_date)) {
            return back()->with('error', 'Its Income & Expenses entry is in a closed month — ' . Transaction::lockReason($entry->transaction->transaction_date));
        }

        $item = $entry->item;
        DB::transaction(function () use ($entry, $item) {
            InventoryItem::lockForUpdate()->find($item->id);
            $transaction = $entry->transaction;
            $entry->delete();
            if ($problem = $this->stock->problem($item)) {
                throw ValidationException::withMessages(['entry' => "Can't remove it: {$problem} Remove the later entries first."]);
            }
            // Its Income & Expenses entry goes with it (refused if that month is closed).
            $transaction?->delete();
        });

        return back()->with('success', "Removed: {$entry->typeLabel()} · " . InventoryStock::qty($entry->quantity) . " {$item->unit} {$item->name}.");
    }

    /** Every place things have been used, plus the zones. */
    protected function locations()
    {
        return InventoryMovement::whereNotNull('location')->distinct()->orderBy('location')->pluck('location')
            ->merge(Zone::names())->unique(fn ($l) => mb_strtolower($l))->sort()->values();
    }
}
