<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Services\InventoryStock;
use Illuminate\Http\Request;

/** The owners' view: what the company has, where it is, what moved. */
class InventoryController extends Controller
{
    public function __construct(private InventoryStock $stock)
    {
    }

    public function summary(Request $request)
    {
        $data = [
            's' => $this->stock->summary(),
            'trend' => $this->stock->trend(6),
            'recent' => InventoryMovement::with(['item', 'recordedBy'])->latest('date')->latest('id')->limit(10)->get(),
        ];

        return view($request->boolean('print') ? 'inventory.print' : 'inventory.summary', $data);
    }

    /** Company assets by place: the store and every location things are used at. */
    public function assets(Request $request)
    {
        $items = InventoryItem::with('category')->get()->keyBy('id');
        $figures = $this->stock->figures();
        $kind = $request->query('kind', 'asset');

        $places = $this->stock->atLocations()
            ->filter(fn ($r) => $items->has($r->inventory_item_id) && ($kind === 'all' || $items[$r->inventory_item_id]->kind === $kind))
            ->map(function ($r) use ($items) {
                $r->item = $items[$r->inventory_item_id];

                return $r;
            })
            ->groupBy(fn ($r) => mb_strtolower(trim($r->location)))
            ->map(fn ($rows) => [
                'location' => $rows->first()->location,
                'rows' => $rows->sortByDesc('value')->values(),
                'value' => $rows->sum('value'),
                'last' => $rows->max('last_date'),
            ])
            ->when($request->filled('q'), fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p['location']), mb_strtolower($request->q))
                || $p['rows']->contains(fn ($r) => str_contains(mb_strtolower($r->item->name . ' ' . $r->item->model), mb_strtolower($request->q)))))
            ->sortByDesc('value')->values();

        $store = $items->map(fn ($i) => ['item' => $i, 'f' => $figures->get($i->id)])
            ->filter(fn ($r) => $r['f'] && $r['f']->on_hand > 0 && ($kind === 'all' || $r['item']->kind === $kind))
            ->sortByDesc(fn ($r) => $r['f']->stock_value)->values();

        return view('inventory.assets', [
            'places' => $places,
            'store' => $store,
            'kind' => $kind,
            's' => $this->stock->summary(),
        ]);
    }
}
