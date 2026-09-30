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
        'product_id',
        'customer_type',
        'package_rate',
        'kam_name',
        'kam_phone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'package_rate' => 'decimal:2',
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

    /**
     * MAC clients are billed a fixed product/package.
     */
    public function usesPackage(): bool
    {
        return ! $this->isBandwidthClient();
    }

    public function customerTypeLabel(): string
    {
        return self::TYPES[$this->customer_type] ?? self::TYPES['mac_client'];
    }

    public function packages()
    {
        return $this->hasMany(CustomerPackage::class)->with('product');
    }

    public function givenProducts()
    {
        return $this->hasMany(CustomerProduct::class)->orderByDesc('given_on')->orderByDesc('id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function bandwidthRates()
    {
        return $this->hasMany(CustomerBandwidthRate::class);
    }

    public function bandwidthRatesTotal(): float
    {
        return (float) $this->bandwidthRates->sum(fn ($rate) => $rate->lineTotal());
    }

    /**
     * Total unpaid balance on record for this customer — their running
     * ledger. Pass an invoice id to exclude it (used on that invoice's own
     * print page, where "previous due" should mean everything except the
     * invoice currently being viewed).
     */
    public function outstandingDue(?int $excludingInvoiceId = null): float
    {
        return (float) $this->invoices()
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->when($excludingInvoiceId, fn ($q) => $q->where('id', '!=', $excludingInvoiceId))
            ->get()
            ->sum(fn (Invoice $invoice) => $invoice->remainingDue());
    }
}
