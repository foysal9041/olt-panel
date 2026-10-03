<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock, cost and where things are — all worked out from the movements.
 *
 * - In the store: opening + purchase + return + count(+)
 *                 − use − sale − count(−) − damage (from the store).
 * - Average cost: what the opening stock, purchases and count(+) cost,
 *   per unit. Use, sale and damage are valued at it when they're entered.
 * - At a location: use − return − damage (at that location).
 * - Company assets: the store at average cost + "asset" items in use.
 *   Consumables in use are used up — shown, but not counted as assets.
 */
class InventoryStock
{
    /**
     * Figures per item id.
     *
     * @return Collection<int, object>
     */
    public function figures(?array $itemIds = null): Collection
    {
        $q = fn (string $cond, string $what = 'quantity') => "SUM(CASE WHEN {$cond} THEN {$what} ELSE 0 END)";

        $rows = InventoryMovement::query()
            ->when($itemIds !== null, fn ($w) => $w->whereIn('inventory_item_id', $itemIds))
            ->groupBy('inventory_item_id')
            ->selectRaw('inventory_item_id')
            ->selectRaw($q("type IN ('opening','purchase','return','adjust_in')") . ' as in_qty')
            ->selectRaw($q("type IN ('use','sale','adjust_out') OR (type = 'damage' AND location IS NULL)") . ' as out_qty')
            ->selectRaw($q("type IN ('opening','purchase','adjust_in')") . ' as costed_qty')
            ->selectRaw($q("type IN ('opening','purchase','adjust_in')", 'quantity * unit_cost') . ' as costed_value')
            ->selectRaw($q("type = 'purchase'") . ' as purchased_qty')
            ->selectRaw($q("type = 'purchase'", 'quantity * unit_cost') . ' as purchased_value')
            ->selectRaw($q("type = 'use'") . ' as used_qty')
            ->selectRaw($q("type = 'use'", 'quantity * unit_cost') . ' - ' . $q("type = 'return' OR (type = 'damage' AND location IS NOT NULL)", 'quantity * unit_cost') . ' as deployed_value')
            ->selectRaw($q("type = 'use'") . ' - ' . $q("type = 'return' OR (type = 'damage' AND location IS NOT NULL)") . ' as deployed_qty')
            ->selectRaw($q("type = 'sale'") . ' as sold_qty')
            ->selectRaw($q("type = 'sale'", 'quantity * unit_price') . ' as sold_value')
            ->selectRaw($q("type = 'sale'", 'quantity * unit_cost') . ' as sold_cost')
            ->selectRaw($q("type = 'damage'") . ' as damaged_qty')
            ->selectRaw($q("type = 'damage'", 'quantity * unit_cost') . ' as damaged_value')
            ->get()
            ->keyBy('inventory_item_id');

        return $rows->map(function ($r) {
            $f = (object) array_map('floatval', $r->only([
                'in_qty', 'out_qty', 'costed_qty', 'costed_value', 'purchased_qty', 'purchased_value', 'used_qty', 'deployed_qty',
                'deployed_value', 'sold_qty', 'sold_value', 'sold_cost', 'damaged_qty', 'damaged_value',
            ]));
            $f->on_hand = round($f->in_qty - $f->out_qty, 2);
            $f->avg_cost = $f->costed_qty > 0 ? round($f->costed_value / $f->costed_qty, 2) : 0.0;
            $f->stock_value = round($f->on_hand * $f->avg_cost, 2);

            return $f;
        });
    }

    /** Figures for one item, zeros when it has no movements yet. */
    public function forItem(InventoryItem $item): object
    {
        return $this->figures([$item->id])->get($item->id) ?? $this->blank();
    }

    public function blank(): object
    {
        return (object) array_fill_keys(['in_qty', 'out_qty', 'costed_qty', 'costed_value', 'purchased_qty', 'purchased_value', 'used_qty',
            'deployed_qty', 'deployed_value', 'sold_qty', 'sold_value', 'sold_cost', 'damaged_qty', 'damaged_value', 'on_hand', 'avg_cost', 'stock_value'], 0.0);
    }

    /**
     * What is at each location now: one row per item and location.
     *
     * @return Collection<int, object{inventory_item_id: int, location: string, qty: float, value: float, last_date: string}>
     */
    public function atLocations(?int $itemId = null): Collection
    {
        $sign = "CASE type WHEN 'use' THEN 1 ELSE -1 END";

        return InventoryMovement::query()
            ->whereIn('type', ['use', 'return', 'damage'])
            ->whereNotNull('location')
            ->when($itemId, fn ($w) => $w->where('inventory_item_id', $itemId))
            ->groupBy('inventory_item_id', 'location')
            ->selectRaw('inventory_item_id, MIN(location) as location')
            ->selectRaw("SUM(({$sign}) * quantity) as qty")
            ->selectRaw("SUM(({$sign}) * quantity * unit_cost) as value")
            ->selectRaw('MAX(date) as last_date')
            ->havingRaw('ABS(qty) > 0.001')
            ->get()
            ->map(function ($r) {
                $r->qty = round((float) $r->qty, 2);
                $r->value = round((float) $r->value, 2);

                return $r;
            });
    }

    /** How many of an item are at one location, and their cost per unit. */
    public function atLocation(int $itemId, string $location): array
    {
        $row = $this->atLocations($itemId)->first(fn ($r) => mb_strtolower(trim($r->location)) === mb_strtolower(trim($location)));

        return $row ? ['qty' => $row->qty, 'unit_cost' => $row->qty > 0 ? round($row->value / $row->qty, 2) : 0.0, 'location' => $row->location]
            : ['qty' => 0.0, 'unit_cost' => 0.0, 'location' => trim($location)];
    }

    /** Why the item's numbers don't add up (stock below zero), if they don't. */
    public function problem(InventoryItem $item): ?string
    {
        $f = $this->forItem($item);
        if ($f->on_hand < -0.001) {
            return "Not enough {$item->name} in the store — this would leave " . self::qty($f->on_hand) . " {$item->unit}.";
        }
        $short = $this->atLocations($item->id)->first(fn ($r) => $r->qty < -0.001);
        if ($short) {
            return "Not that many {$item->name} at {$short->location} — this would leave " . self::qty($short->qty) . " {$item->unit} there.";
        }

        return null;
    }

    /**
     * Totals for the summary page and the dashboard.
     */
    public function summary(): array
    {
        $items = InventoryItem::with('category')->get()->keyBy('id');
        $figures = $this->figures();
        $get = fn (int $id) => $figures->get($id) ?? $this->blank();

        $storeValue = 0.0;
        $inUseAssets = 0.0;
        $usedUp = 0.0;
        $low = collect();
        $byCategory = [];

        foreach ($items as $item) {
            $f = $get($item->id);
            $storeValue += $f->stock_value;
            if ($item->isAsset()) {
                $inUseAssets += $f->deployed_value;
            } else {
                $usedUp += $f->deployed_value;
            }
            $cat = $item->category?->name ?? 'Other';
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $f->stock_value + ($item->isAsset() ? $f->deployed_value : 0);
            if ($item->is_active && (float) $item->min_stock > 0 && $f->on_hand <= (float) $item->min_stock) {
                $low->push(['item' => $item, 'on_hand' => $f->on_hand]);
            }
        }
        arsort($byCategory);

        $month = [now()->startOfMonth()->toDateString(), now()->toDateString()];
        $year = [now()->startOfYear()->toDateString(), now()->toDateString()];
        $sumBetween = fn (string $type, array $range, string $what) => (float) InventoryMovement::where('type', $type)->whereBetween('date', $range)->sum(DB::raw($what));

        $locations = $this->atLocations()
            ->filter(fn ($r) => $items->get($r->inventory_item_id)?->isAsset())
            ->groupBy(fn ($r) => mb_strtolower(trim($r->location)))
            ->map(fn ($g) => ['location' => $g->first()->location, 'value' => $g->sum('value'), 'items' => $g->count(), 'qty' => $g->sum('qty')])
            ->sortByDesc('value')->values();

        return [
            'store_value' => round($storeValue, 2),
            'in_use_value' => round($inUseAssets, 2),
            'company_assets' => round($storeValue + $inUseAssets, 2),
            'used_up_value' => round($usedUp, 2),
            'items' => $items->count(),
            'items_in_stock' => $figures->filter(fn ($f) => $f->on_hand > 0)->count(),
            'low' => $low->sortBy('on_hand')->values(),
            'by_category' => $byCategory,
            'locations' => $locations,
            'month_purchase' => $sumBetween('purchase', $month, 'quantity * unit_cost'),
            'month_sales' => $sumBetween('sale', $month, 'quantity * unit_price'),
            'month_sales_cost' => $sumBetween('sale', $month, 'quantity * unit_cost'),
            'year_damage' => $sumBetween('damage', $year, 'quantity * unit_cost'),
        ];
    }

    /** Purchases and sales per month, oldest first. */
    public function trend(int $months = 6): array
    {
        return collect(range($months - 1, 0))->map(function ($i) {
            $start = Carbon::today()->subMonthsNoOverflow($i)->startOfMonth();
            $range = [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];

            return [
                'label' => $start->format('M'),
                'purchase' => round((float) InventoryMovement::where('type', 'purchase')->whereBetween('date', $range)->sum(DB::raw('quantity * unit_cost')), 2),
                'sale' => round((float) InventoryMovement::where('type', 'sale')->whereBetween('date', $range)->sum(DB::raw('quantity * unit_price')), 2),
                'used' => round((float) InventoryMovement::where('type', 'use')->whereBetween('date', $range)->sum(DB::raw('quantity * unit_cost')), 2),
            ];
        })->all();
    }

    /** 12.50 → "12.5", 3.00 → "3" */
    public static function qty($v): string
    {
        $s = number_format((float) $v, 2, '.', ',');

        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }
}
