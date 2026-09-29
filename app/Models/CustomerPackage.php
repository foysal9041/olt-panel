<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class CustomerPackage extends Model
{
    use LogsActivity;

    protected $fillable = ['customer_id', 'product_id', 'pricing', 'rate', 'commission_percent', 'quantity', 'is_active'];

    protected $casts = [
        'rate' => 'float',
        'commission_percent' => 'float',
        'quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * What the client pays per user: the fixed rate, or the package's list
     * price less their commission.
     */
    public function unitPrice(): float
    {
        if ($this->pricing === 'commission') {
            $price = (float) ($this->product?->price ?? 0);

            return round($price * (1 - ((float) $this->commission_percent) / 100), 2);
        }

        return round((float) $this->rate, 2);
    }

    public function monthlyTotal(?int $quantity = null): float
    {
        return round($this->unitPrice() * ($quantity ?? $this->quantity), 2);
    }

    public function pricingLabel(): string
    {
        return $this->pricing === 'commission'
            ? rtrim(rtrim(number_format((float) $this->commission_percent, 2), '0'), '.') . '% commission'
            : 'Fixed ৳' . number_format((float) $this->rate, 2);
    }
}
