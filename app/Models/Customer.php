<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'username',
        'phone',
        'address',
        'zone',
        'customer_type',
        'kam_name',
        'kam_phone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    /** The two kinds of customer. */
    public const TYPES = [
        'mac_client' => 'MAC Client',
        'bandwidth_client' => 'Bandwidth Client',
    ];

    /**
     * Bandwidth clients are billed per bandwidth type (rate × quantity).
     */
    public function isBandwidthClient(): bool
    {
        return $this->customer_type === 'bandwidth_client';
    }

    public function customerTypeLabel(): string
    {
        return self::TYPES[$this->customer_type] ?? self::TYPES['mac_client'];
    }

    /** This customer's lines in the monthly zone settlements, newest month first. */
    public function settlementRows()
    {
        return $this->hasMany(ZoneSettlementRow::class)
            ->join('zone_settlements', 'zone_settlements.id', '=', 'zone_settlement_rows.zone_settlement_id')
            ->orderByDesc('zone_settlements.month')
            ->select('zone_settlement_rows.*');
    }

    public function bandwidthRates()
    {
        return $this->hasMany(CustomerBandwidthRate::class);
    }

    public function bandwidthRatesTotal(): float
    {
        return (float) $this->bandwidthRates->sum(fn ($rate) => $rate->lineTotal());
    }
}
