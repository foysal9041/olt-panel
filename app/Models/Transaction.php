<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use LogsActivity;

    protected $fillable = [
        'invoice_id',
        'transaction_category_id',
        'amount',
        'description',
        'transaction_date',
        'recorded_by',
        'zone',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function category()
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The invoice this payment was recorded against, if any — an invoice
     * can have several of these when it's paid off in installments.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopeIncome($query)
    {
        return $query->whereHas('category', fn ($q) => $q->where('type', 'income'));
    }

    public function scopeExpense($query)
    {
        return $query->whereHas('category', fn ($q) => $q->where('type', 'expense'));
    }
}
