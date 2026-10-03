<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\ZoneSettlement;
use App\Support\Dec;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Suggests a month's profit sheet from what the panel already knows:
 *
 *   Bill income   zone settlements' Net Bill per cycle (Fixed date, Balunda,
 *                 NTTN) and what each bandwidth client paid in the month
 *   Others income Cash Book / Income & Expenses income of the month, by head
 *   Fixed / Others cost  expenses of the month, by head, as each head is set
 *                 (heads set to "Not counted" — e.g. Partner Payout — stay out)
 *
 * Commission holders and shareholders are not part of it: a draft always
 * uses Partners as they are.
 */
class ProfitSheetBuilder
{
    public function __construct(private BandwidthBilling $billing)
    {
    }

    /**
     * @return list<array{section: string, label: string, amount: string, source: string}>
     */
    public function suggest(Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();
        $range = [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()];
        $lines = [];

        // Zone settlements: one line per cycle, even when not uploaded yet.
        $settlements = ZoneSettlement::with('rows')->whereDate('month', $month)->get()->groupBy('cycle');
        foreach (ZoneSettlement::CYCLES as $key => $cycle) {
            $net = '0';
            foreach ($settlements[$key] ?? [] as $s) {
                $net = Dec::add($net, $s->totals()['income']);
            }
            $lines[] = ['section' => 'bill', 'label' => $cycle['line'], 'amount' => Dec::round($net, 2), 'source' => "settlement:{$key}"];
        }

        // Bandwidth clients: what they actually paid this month (Bandwidth
        // Billing); the part that cleared an earlier month's due is its own line.
        foreach ($this->billing->collections($month) as $c) {
            if ((float) $c['current'] != 0) {
                $lines[] = ['section' => 'bill', 'label' => $c['customer']->name, 'amount' => $c['current'], 'source' => "bw:{$c['customer']->id}"];
            }
            if ((float) $c['previous'] != 0) {
                $lines[] = ['section' => 'bill', 'label' => 'Previous Month Bill — ' . $c['customer']->name, 'amount' => $c['previous'], 'source' => "bwprev:{$c['customer']->id}"];
            }
        }

        // Cash Book / Income & Expenses entries of the month, by head.
        $heads = Transaction::query()
            ->join('transaction_categories as c', 'c.id', '=', 'transactions.transaction_category_id')
            ->whereBetween('transaction_date', $range)
            ->groupBy('c.id', 'c.name', 'c.type', 'c.pl_group')
            ->orderBy('c.name')
            ->get(['c.id', 'c.name', 'c.type', 'c.pl_group', DB::raw('SUM(transactions.amount) as total')]);

        foreach ($heads as $h) {
            $group = $h->pl_group ?: 'other';
            $section = match (true) {
                $group === 'none' => null,
                $h->type === 'income' => 'other_income',
                $group === 'fixed' => 'fixed_cost',
                default => 'other_cost',
            };

            if ($section) {
                $lines[] = ['section' => $section, 'label' => \App\Support\Ui::english($h->name), 'amount' => Dec::round($h->total, 2), 'source' => "category:{$h->id}"];
            }
        }

        return $lines;
    }
}
