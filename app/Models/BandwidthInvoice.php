<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;

/**
 * One bandwidth client's bill for a month (made on the 1st):
 *
 *   Total Bill  = its lines
 *   Total MRC   = Total Bill + previous due (everything billed before minus
 *                 everything paid against earlier invoices, plus the
 *                 opening due)
 *   Due         = Total MRC − payments recorded against this invoice
 */
class BandwidthInvoice extends Model
{
    use LogsActivity;

    protected $fillable = ['customer_id', 'month', 'invoice_no', 'invoice_date', 'due_date', 'prepared_by', 'prepared_title', 'notes', 'created_by'];

    protected $casts = [
        'month' => 'date',
        'invoice_date' => 'date',
        'due_date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines()
    {
        return $this->hasMany(BandwidthInvoiceLine::class)->orderBy('sort')->orderBy('id');
    }

    public function payments()
    {
        return $this->hasMany(BandwidthPayment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function totalBill(): string
    {
        return Dec::round(Dec::add('0', ...$this->lines->pluck('amount')), 2);
    }

    public function previousDue(): string
    {
        $earlier = self::where('customer_id', $this->customer_id)->where('month', '<', $this->month->toDateString())->with('lines')->get();
        $billed = Dec::add('0', ...$earlier->map(fn ($i) => $i->totalBill()));
        // Paid against earlier bills, or before this month without a bill to go to.
        $paid = (string) BandwidthPayment::where('customer_id', $this->customer_id)
            ->where(fn ($q) => $q->whereIn('bandwidth_invoice_id', $earlier->pluck('id'))
                ->orWhere(fn ($q) => $q->whereNull('bandwidth_invoice_id')->where('paid_on', '<', $this->month->toDateString())))
            ->sum('amount');

        return Dec::round(Dec::sub(Dec::add($this->customer->opening_due ?? 0, $billed), $paid), 2);
    }

    public function paid(): string
    {
        return Dec::round(Dec::add('0', ...$this->payments->pluck('amount')), 2);
    }

    /** @return array{bill: string, previous: string, mrc: string, paid: string, due: string} */
    public function figures(): array
    {
        $bill = $this->totalBill();
        $previous = $this->previousDue();
        $mrc = Dec::add($bill, $previous);
        $paid = $this->paid();

        return ['bill' => $bill, 'previous' => $previous, 'mrc' => Dec::round($mrc, 2), 'paid' => $paid, 'due' => Dec::round(Dec::sub($mrc, $paid), 2)];
    }

    public function isClosed(): bool
    {
        return ProfitSheet::isMonthClosed($this->month);
    }

    protected function activityLogLabel(): string
    {
        return 'Bandwidth Invoice';
    }

    protected function activityLogTitle(): string
    {
        return $this->invoice_no . ' · ' . ($this->customer?->name ?? '');
    }
}
