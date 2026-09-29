<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class CustomerProduct extends Model
{
    use LogsActivity;

    protected $fillable = ['customer_id', 'product_id', 'name', 'quantity', 'unit_price', 'chargeable', 'given_on', 'note', 'invoice_id'];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'float',
        'chargeable' => 'boolean',
        'given_on' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function total(): float
    {
        return round($this->quantity * $this->unit_price, 2);
    }

    /** Chargeable and not on any invoice yet. */
    public function isPending(): bool
    {
        return $this->chargeable && ! $this->invoice_id && $this->total() > 0;
    }
}
