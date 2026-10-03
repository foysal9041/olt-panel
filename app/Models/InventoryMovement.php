<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use LogsActivity;

    /**
     * type => [label, direction for the store, icon, colour].
     * "use" moves stock from the store to a location; "return" brings it
     * back; "damage" can be from the store or from a location.
     */
    public const TYPES = [
        'opening' => ['Opening stock', 'in', 'fas fa-box-open', '#64748b'],
        'purchase' => ['Purchase', 'in', 'fas fa-cart-plus', '#16a34a'],
        'use' => ['Used / installed', 'out', 'fas fa-tools', '#4f46e5'],
        'return' => ['Returned to store', 'in', 'fas fa-undo-alt', '#0284c7'],
        'sale' => ['Sold', 'out', 'fas fa-hand-holding-usd', '#d97706'],
        'damage' => ['Damaged / lost', 'out', 'fas fa-heart-broken', '#dc2626'],
        'adjust_in' => ['Stock count +', 'in', 'fas fa-plus-circle', '#0f766e'],
        'adjust_out' => ['Stock count −', 'out', 'fas fa-minus-circle', '#9f1239'],
    ];

    /** Types whose cost is the price paid (the rest use the average cost). */
    public const COSTED = ['opening', 'purchase', 'adjust_in'];

    protected $fillable = [
        'inventory_item_id',
        'type',
        'quantity',
        'unit_cost',
        'unit_price',
        'date',
        'party',
        'customer_id',
        'location',
        'reference',
        'serials',
        'note',
        'transaction_id',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function isIn(): bool
    {
        return (self::TYPES[$this->type][1] ?? 'in') === 'in';
    }

    /** Value of the movement: sale price for sales, cost otherwise. */
    public function value(): float
    {
        return (float) $this->quantity * (float) ($this->type === 'sale' ? $this->unit_price : $this->unit_cost);
    }

    protected function activityLogTitle(): string
    {
        return $this->typeLabel() . ' · ' . rtrim(rtrim((string) $this->quantity, '0'), '.') . ' × ' . ($this->item?->name ?? "item #{$this->inventory_item_id}");
    }
}
