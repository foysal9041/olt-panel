<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use LogsActivity;

    /**
     * Which money moved. All income is deposited in the bank; the office
     * gets petty cash from the bank and pays its expenses out of it. The
     * Cash Book and "cash on hand" are the petty cash only.
     */
    public const ACCOUNTS = ['cash' => 'Petty cash', 'bank' => 'Bank'];

    protected $fillable = [
        'transaction_category_id',
        'account',
        'amount',
        'description',
        'transaction_date',
        'recorded_by',
        'zone',
    ];

    protected static function booted(): void
    {
        $guard = function (Transaction $t) {
            foreach (array_filter([$t->transaction_date, $t->getOriginal('transaction_date')]) as $date) {
                if (ProfitSheet::isMonthClosed($date)) {
                    abort(423, self::lockReason($date));
                }
            }
        };

        static::saving($guard);
        static::deleting($guard);
    }

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

    /** Entries that moved the office's cash (the Cash Book). */
    public function scopeCash($query)
    {
        return $query->where('transactions.account', 'cash');
    }

    /**
     * Real income and costs only: leaves out heads set to "Not counted"
     * (petty cash brought from the bank, loans, transfers).
     */
    public function scopeCounted($query)
    {
        return $query->whereHas('category', fn ($q) => $q->where(fn ($w) => $w->whereNull('pl_group')->orWhere('pl_group', '!=', 'none')));
    }

    /** Is this an income head that counts as income (so it belongs in the bank)? */
    public static function isRealIncome(?TransactionCategory $category): bool
    {
        return $category?->type === 'income' && $category->plGroup() !== 'none';
    }

    public function accountLabel(): string
    {
        return self::ACCOUNTS[$this->account] ?? self::ACCOUNTS['cash'];
    }

    public function scopeIncome($query)
    {
        return $query->whereHas('category', fn ($q) => $q->where('type', 'income'));
    }

    public function scopeExpense($query)
    {
        return $query->whereHas('category', fn ($q) => $q->where('type', 'expense'));
    }

    /**
     * Past days are closed books: only an admin may add, change or remove
     * entries dated before today. Today's page stays open to everyone who
     * can use the cash book. A month whose Net Profit is finalized is closed
     * for everyone, admins included, until it is reopened.
     */
    public static function dayIsOpenFor(\DateTimeInterface|string $date, ?User $user): bool
    {
        if (ProfitSheet::isMonthClosed($date)) {
            return false;
        }

        if ($user?->isAdmin()) {
            return true;
        }

        return \Illuminate\Support\Carbon::parse($date)->startOfDay()->gte(\Illuminate\Support\Carbon::today());
    }

    /** Why a day can't be changed, for the error message. */
    public static function lockReason(\DateTimeInterface|string $date): string
    {
        $date = \Illuminate\Support\Carbon::parse($date);

        return ProfitSheet::isMonthClosed($date)
            ? "{$date->format('F Y')} is closed (Net Profit finalized). An admin has to reopen the month first."
            : 'Only an admin can change entries for past days.';
    }

    public function isLockedFor(?User $user): bool
    {
        return ! self::dayIsOpenFor($this->transaction_date, $user);
    }
}
