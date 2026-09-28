<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
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

    /**
     * Whether this customer is billed per bandwidth type (rate × quantity)
     * instead of a single fixed package — true for both resellers
     * (bandwidth_client) and corporate accounts (corporate_client), which
     * share the same rate-based billing shape.
     */
    public function isBandwidthClient(): bool
    {
        return in_array($this->customer_type, ['bandwidth_client', 'corporate_client'], true);
    }

    /**
     * Whether this customer is billed on a fixed product/package — true for
     * both retail customers (mac_client) and corporate accounts
     * (corporate_client), which can carry a package on top of their
     * bandwidth rates.
     */
    public function usesPackage(): bool
    {
        return in_array($this->customer_type, ['mac_client', 'corporate_client'], true);
    }

    public function customerTypeLabel(): string
    {
        return match ($this->customer_type) {
            'bandwidth_client' => 'Bandwidth Client',
            'corporate_client' => 'Corporate Customer',
            default => 'Mac Client',
        };
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
