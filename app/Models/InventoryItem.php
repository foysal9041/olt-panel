<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use LogsActivity;

    /** asset: still the company's where it's used · consumable: used up. */
    public const KINDS = [
        'asset' => 'Company asset',
        'consumable' => 'Consumable',
    ];

    public const UNITS = ['pcs', 'meter', 'km', 'box', 'roll', 'set', 'pair', 'kg', 'litre'];

    protected $fillable = [
        'inventory_category_id',
        'name',
        'brand',
        'model',
        'unit',
        'kind',
        'min_stock',
        'sale_price',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_stock' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /** "ONU — Huawei HG8145V5": name with brand and model. */
    public function fullName(): string
    {
        $extra = trim(($this->brand && ! str_contains($this->name, $this->brand) ? $this->brand . ' ' : '') . ($this->model ?? ''));

        return $extra !== '' ? "{$this->name} — {$extra}" : $this->name;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function isAsset(): bool
    {
        return $this->kind === 'asset';
    }
}
