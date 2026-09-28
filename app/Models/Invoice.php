<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use LogsActivity;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'product_id',
        'amount',
        'billing_month',
        'status',
        'issued_at',
        'paid_at',
        'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The most recent payment recorded against this invoice — kept for
     * quick display; the full history is payments().
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Every payment recorded against this invoice — a customer can settle
     * one invoice across several installments instead of all at once.
     */
    public function payments()
    {
        return $this->hasMany(Transaction::class)->orderBy('transaction_date')->orderBy('id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === 'partially_paid';
    }

    public function amountPaid(): float
    {
        return (float) ($this->relationLoaded('payments')
            ? $this->payments->sum('amount')
            : $this->payments()->sum('amount'));
    }

    public function remainingDue(): float
    {
        return max(0.0, (float) $this->amount - $this->amountPaid());
    }

    /**
     * Records a payment against this invoice for $amount (defaulting to
     * whatever's still owed — the "pay it off in full" case) and updates
     * status to unpaid/partially_paid/paid based on the running total.
     */
    public function recordPayment(?float $amount = null, ?string $paymentDate = null, ?string $note = null): Transaction
    {
        $amount ??= $this->remainingDue();

        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Product / Bandwidth Sales', 'type' => 'income']
        );

        $description = "Invoice {$this->invoice_number} — {$this->customer->name}";

        if ($note) {
            $description .= " ({$note})";
        }

        $transaction = Transaction::create([
            'invoice_id' => $this->id,
            'transaction_category_id' => $category->id,
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => $paymentDate ?? now()->toDateString(),
            'recorded_by' => auth()->id(),
            'zone' => $this->customer->zone,
        ]);

        $this->recalculateStatus();

        return $transaction;
    }

    /**
     * Re-derives status/paid_at/transaction_id from the current set of
     * payments — called after a payment is recorded, edited, or deleted,
     * since any of those can move the invoice between unpaid, partially
     * paid, and paid.
     */
    public function recalculateStatus(): void
    {
        $this->unsetRelation('payments');
        $remaining = $this->remainingDue();
        $latestPayment = $this->payments()->latest('transaction_date')->latest('id')->first();

        $this->update([
            'status' => $remaining <= 0 && $latestPayment ? 'paid' : ($latestPayment ? 'partially_paid' : 'unpaid'),
            'paid_at' => ($remaining <= 0 && $latestPayment) ? $latestPayment->transaction_date : null,
            'transaction_id' => $latestPayment?->id,
        ]);
    }
}
