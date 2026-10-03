<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'contact_person',
        'username',
        'phone',
        'address',
        'zone',
        'customer_type',
        'kam_name',
        'kam_phone',
        'status',
        'opening_due',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'opening_due' => 'string',
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

    public function serviceChanges()
    {
        return $this->hasMany(BandwidthServiceChange::class)->orderBy('effective_from')->orderBy('id');
    }

    public function bandwidthInvoices()
    {
        return $this->hasMany(BandwidthInvoice::class)->orderByDesc('month');
    }

    public function bandwidthPayments()
    {
        return $this->hasMany(BandwidthPayment::class)->orderBy('paid_on')->orderBy('id');
    }

    /** Opening due + everything billed − everything paid. */
    public function bandwidthBalance(): string
    {
        $billed = \App\Support\Dec::add('0', ...BandwidthInvoice::where('customer_id', $this->id)->with('lines')->get()->map->totalBill());
        $paid = (string) BandwidthPayment::where('customer_id', $this->id)->sum('amount');

        return \App\Support\Dec::round(\App\Support\Dec::sub(\App\Support\Dec::add($this->opening_due ?? 0, $billed), $paid), 2);
    }

    public function bandwidthRatesTotal(): float
    {
        return (float) $this->bandwidthRates->sum(fn ($rate) => $rate->lineTotal());
    }
}
