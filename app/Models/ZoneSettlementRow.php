<?php

namespace App\Models;

use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;

/**
 * One zone's line in a settlement:
 *   Customer Invoice   = Total Payment + Deduction   (Deduction is negative)
 *   Payment Difference = Total Payment − Customer Invoice
 *   bKash Charge       = Total Payment × bKash %
 *   Company Income     = Payment Difference − bKash Charge
 */
class ZoneSettlementRow extends Model
{
    protected $fillable = [
        'zone_settlement_id', 'source_row', 'name', 'customer_id', 'total_payment', 'deduction', 'included', 'flags', 'invoice_no',
    ];

    protected $casts = [
        'total_payment' => 'string',
        'deduction' => 'string',
        'included' => 'boolean',
        'flags' => 'array',
    ];

    public function settlement()
    {
        return $this->belongsTo(ZoneSettlement::class, 'zone_settlement_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Name to show / bill to: the linked customer, else the zone part of
     * "Navaron - (Zone) (habibur@sunlit)", else the name as in the sheet.
     */
    public function displayName(): string
    {
        return $this->customer?->name ?? \App\Services\SettlementImporter::splitName($this->name)[0] ?: (string) $this->name;
    }

    /** The billing username from the sheet's name, if it has one. */
    public function username(): ?string
    {
        return \App\Services\SettlementImporter::splitName($this->name)[1] ?? $this->customer?->username;
    }

    /**
     * @return array{payment:string, deduction:string, invoice:string, difference:string, bkash:string, income:string}
     */
    public function calc(string|float|null $bkashPercent = null): array
    {
        return self::compute($this->total_payment, $this->deduction, $bkashPercent ?? $this->settlement?->bkash_percent ?? '1.5');
    }

    /**
     * @return array{payment:string, deduction:string, invoice:string, difference:string, bkash:string, income:string}
     */
    public static function compute($payment, $deduction, $bkashPercent): array
    {
        $payment = Dec::of($payment);
        $deduction = Dec::of($deduction);
        $invoice = Dec::add($payment, $deduction);
        $difference = Dec::sub($payment, $invoice);
        $bkash = Dec::mul($payment, Dec::div($bkashPercent, 100));
        $income = Dec::sub($difference, $bkash);

        return compact('payment', 'deduction', 'invoice', 'difference', 'bkash', 'income');
    }
}
