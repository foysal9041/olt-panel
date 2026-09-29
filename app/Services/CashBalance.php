<?php

namespace App\Services;

use App\Models\CashOpening;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cash in hand at the start of a day (জের).
 *
 * Starts from the latest "cash on hand" count on or before that day (0 if
 * none), then adds income and subtracts expenses from that count's date up
 * to the day before.
 */
class CashBalance
{
    /**
     * @return array{amount: float, base: ?CashOpening}
     */
    public static function before(Carbon $date): array
    {
        $base = CashOpening::whereDate('date', '<=', $date->toDateString())->orderByDesc('date')->first();

        $row = DB::table('transactions')
            ->join('transaction_categories as c', 'c.id', '=', 'transactions.transaction_category_id')
            ->where('transactions.transaction_date', '<', $date->toDateString())
            ->when($base, fn ($q) => $q->where('transactions.transaction_date', '>=', $base->date->toDateString()))
            ->selectRaw("COALESCE(SUM(CASE WHEN c.type = 'income' THEN transactions.amount END), 0) AS i,
                         COALESCE(SUM(CASE WHEN c.type = 'expense' THEN transactions.amount END), 0) AS e")
            ->first();

        return [
            'amount' => round(($base?->amount ?? 0) + (float) $row->i - (float) $row->e, 2),
            'base' => $base,
        ];
    }
}
