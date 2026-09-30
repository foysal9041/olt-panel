<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class CustomerBandwidthRate extends Model
{
    use LogsActivity;

    protected $fillable = [
        'customer_id',
        'bandwidth_type_id',
        'rate',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'quantity' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function bandwidthType()
    {
        return $this->belongsTo(BandwidthType::class);
    }

    public function lineTotal(): float
    {
        return (float) $this->rate * (float) $this->quantity;
    }

    protected function activityLogLabel(): string
    {
        return 'Bandwidth Rate';
    }

    protected function activityLogTitle(): string
    {
        return trim(($this->customer?->name ?? 'customer #' . $this->customer_id) . ' · ' . ($this->bandwidthType?->name ?? ''), ' ·');
    }
}
