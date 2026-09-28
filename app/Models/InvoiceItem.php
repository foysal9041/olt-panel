<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'label',
        'amount',
        'rate',
        'quantity',
        'is_adjustment',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'rate' => 'decimal:2',
            'quantity' => 'decimal:2',
            'is_adjustment' => 'boolean',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
