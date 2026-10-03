<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * Money received from a bandwidth client. All income is deposited in the
 * bank, so it never touches the petty cash; the Net Profit sheet takes
 * bandwidth income from here. (transaction_id is only set on payments made
 * before that rule.)
 */
class BandwidthPayment extends Model
{
    use LogsActivity;

    public const METHODS = ['Bank', 'bKash', 'Nagad', 'Cash', 'Other'];

    protected $fillable = ['customer_id', 'bandwidth_invoice_id', 'paid_on', 'amount', 'method', 'received_by', 'reference', 'note', 'transaction_id', 'recorded_by'];

    protected $casts = [
        'paid_on' => 'date',
        'amount' => 'string',
    ];

    /** Money receipt number, e.g. MR-00042. */
    public function receiptNo(): string
    {
        return 'MR-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(BandwidthInvoice::class, 'bandwidth_invoice_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function activityLogLabel(): string
    {
        return 'Bandwidth Payment';
    }

    protected function activityLogTitle(): string
    {
        return ($this->customer?->name ?? '#' . $this->customer_id) . ' · ' . $this->amount . ' on ' . $this->paid_on?->format('d M Y');
    }
}
