<?php

namespace App\Services;

use App\Models\BandwidthInvoice;
use App\Models\BandwidthPayment;
use App\Models\BandwidthServiceChange;
use App\Models\BandwidthType;
use App\Models\Customer;
use App\Models\ProfitSheet;
use App\Models\User;
use App\Support\Dec;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bandwidth client billing:
 *
 *   - lines(): a month's bill from the client's service history. Billing
 *     is flat monthly: a full month is rate × Mbps whatever its length. A
 *     per-Mbps type changed mid-month (rate revised, upgraded, downgraded)
 *     becomes one line per part, each its share of the month's days, e.g.
 *     "1 Aug to 15 Aug" and "16 Aug to 31 Aug". Fixed types (VAS, Billing)
 *     are one line at the month-end values.
 *   - generate(): the month's invoice (normally on the 1st).
 *   - collections(): what each client paid in a month, split into what
 *     cleared earlier dues and what went to that month's bill — the Net
 *     Profit sheet's bandwidth lines.
 */
class BandwidthBilling
{
    public const DEFAULT_SIGNATORY = ['Md Habibur Rahman', 'Chairman'];

    /**
     * @return list<array{bandwidth_type_id: int, label: string, period_from: string, period_to: string, rate: string, mbps: ?string, amount: string, remark: ?string, sort: int}>
     */
    public function lines(Customer $customer, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $daysInMonth = $start->daysInMonth;

        $changes = BandwidthServiceChange::where('customer_id', $customer->id)
            ->whereDate('effective_from', '<=', $end)
            ->orderBy('effective_from')->orderBy('id')
            ->get()->groupBy('bandwidth_type_id');

        $types = BandwidthType::whereIn('id', $changes->keys())->ordered()->get();
        $lines = [];

        foreach ($types as $type) {
            $rows = $changes[$type->id];

            // What applied on the 1st, then each change inside the month.
            $value = $rows->filter(fn ($r) => $r->effective_from->lte($start))->last();
            $from = $start->copy();
            $segments = [];
            foreach ($rows->filter(fn ($r) => $r->effective_from->gt($start)) as $change) {
                if ($value) {
                    $segments[] = [$from, $change->effective_from->copy()->subDay(), $value];
                }
                $from = $change->effective_from->copy();
                $value = $change;
            }
            if ($value) {
                $segments[] = [$from, $end->copy(), $value];
            }

            if ($type->flat) {
                // A fixed monthly charge: the month-end values, from the day it started.
                $last = end($segments);
                if (! $last || (float) $last[2]->rate <= 0) {
                    continue;
                }
                $active = collect($segments)->first(fn ($s) => (float) $s[2]->rate > 0);
                $lines[] = $this->line($type, $active[0], $end, $last[2], Dec::mul($last[2]->rate, $last[2]->mbps ?: 1));

                continue;
            }

            foreach ($segments as [$segFrom, $segTo, $row]) {
                if ((float) $row->rate <= 0 || (float) $row->mbps <= 0) {
                    continue;
                }
                $days = (int) $segFrom->diffInDays($segTo) + 1;
                $monthly = Dec::mul($row->rate, $row->mbps);
                $amount = $days === $daysInMonth ? $monthly : Dec::div(Dec::mul($monthly, $days), $daysInMonth);
                $remark = $segFrom->gt($start) ? ($row->note ?: 'Changed from ' . $segFrom->format('j M')) : null;
                $lines[] = $this->line($type, $segFrom, $segTo, $row, $amount, $remark);
            }
        }

        return $lines;
    }

    /**
     * Month by month, from the first rate on record to this month (or the
     * last invoice): each type's Mbps and rate (both values when it changed
     * mid-month), the bill the rates give, and the invoice actually made.
     *
     * @return list<array{month: Carbon, types: array<string, list<array{rate: string, mbps: ?string, from: Carbon, to: Carbon}>>, total: string, invoice: ?BandwidthInvoice}>
     */
    public function monthly(Customer $customer): array
    {
        $first = BandwidthServiceChange::where('customer_id', $customer->id)->min('effective_from');
        if (! $first) {
            return [];
        }

        $invoices = BandwidthInvoice::with('lines')->where('customer_id', $customer->id)->get()->keyBy(fn ($i) => $i->month->format('Y-m'));
        $last = collect([Carbon::today()->startOfMonth(), ...$invoices->map(fn ($i) => $i->month)])->max();
        $out = [];

        for ($m = Carbon::parse($first)->startOfMonth(); $m->lte($last); $m->addMonthNoOverflow()) {
            $lines = $this->lines($customer, $m);
            $types = [];
            foreach ($lines as $l) {
                $types[$l['label']][] = ['rate' => $l['rate'], 'mbps' => $l['mbps'], 'from' => Carbon::parse($l['period_from']), 'to' => Carbon::parse($l['period_to'])];
            }
            $out[] = [
                'month' => $m->copy(),
                'types' => $types,
                'total' => Dec::round(Dec::add('0', ...array_column($lines, 'amount')), 2),
                'invoice' => $invoices[$m->format('Y-m')] ?? null,
            ];
        }

        return array_reverse($out);
    }

    /** Make the month's invoice for a client (once; returns the existing one otherwise). */
    public function generate(Customer $customer, Carbon $month, ?User $by = null, ?Carbon $date = null): BandwidthInvoice
    {
        $month = $month->copy()->startOfMonth();

        return DB::transaction(function () use ($customer, $month, $by, $date) {
            $existing = BandwidthInvoice::where('customer_id', $customer->id)->whereDate('month', $month)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $last = BandwidthInvoice::where('customer_id', $customer->id)->latest('month')->first();
            $invoice = BandwidthInvoice::create([
                'customer_id' => $customer->id,
                'month' => $month,
                'invoice_no' => $this->nextNumber($month),
                'invoice_date' => ($date ?? Carbon::today())->max($month)->toDateString(),
                'due_date' => $month->copy()->day(10)->toDateString(),
                'prepared_by' => $last?->prepared_by ?? self::DEFAULT_SIGNATORY[0],
                'prepared_title' => $last?->prepared_title ?? self::DEFAULT_SIGNATORY[1],
                'created_by' => $by?->id,
            ]);

            $invoice->lines()->createMany($this->lines($customer, $month));

            return $invoice;
        });
    }

    /** Rebuild an invoice's lines from the service history (payments stay). */
    public function recalculate(BandwidthInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice->lines()->delete();
            $invoice->lines()->createMany($this->lines($invoice->customer, $invoice->month));
        });
    }

    /** Sunlit/DC/AUG26/01, numbered per month. */
    public function nextNumber(Carbon $month): string
    {
        $prefix = 'Sunlit/DC/' . strtoupper($month->format('M')) . $month->format('y') . '/';
        $last = BandwidthInvoice::where('invoice_no', 'like', $prefix . '%')->lockForUpdate()->pluck('invoice_no')
            ->map(fn ($no) => (int) substr($no, strlen($prefix)))->max() ?? 0;

        return $prefix . str_pad((string) ($last + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * What each client paid in the month, split into the part that cleared
     * earlier dues (previous month bill) and the part for this month's bill.
     *
     * @return Collection<int, array{customer: Customer, current: string, previous: string}>
     */
    public function collections(Carbon $month): Collection
    {
        $start = $month->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return BandwidthPayment::with('customer')->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()])->get()
            ->groupBy('customer_id')
            ->map(function ($payments) use ($start) {
                $customer = $payments->first()->customer;
                $total = Dec::add('0', ...$payments->pluck('amount'));
                $invoice = BandwidthInvoice::with('customer')->where('customer_id', $customer->id)->whereDate('month', $start)->first();
                $owed = $invoice ? $invoice->previousDue() : $customer->bandwidthBalance();
                $owed = Dec::isNegative($owed) ? '0' : $owed;
                $previous = bccomp($total, $owed, 6) > 0 ? $owed : $total;

                return ['customer' => $customer, 'current' => Dec::round(Dec::sub($total, $previous), 2), 'previous' => Dec::round($previous, 2)];
            })
            ->sortBy(fn ($c) => $c['customer']->name)
            ->values();
    }

    /** The invoice a payment on $date belongs to: that month's, else the latest before it. */
    public function invoiceFor(Customer $customer, Carbon $date): ?BandwidthInvoice
    {
        return BandwidthInvoice::where('customer_id', $customer->id)->whereDate('month', $date->copy()->startOfMonth())->first()
            ?? BandwidthInvoice::where('customer_id', $customer->id)->where('month', '<=', $date->toDateString())->latest('month')->first();
    }

    public function monthIsClosed(Carbon $month): bool
    {
        return ProfitSheet::isMonthClosed($month);
    }

    protected function line(BandwidthType $type, Carbon $from, Carbon $to, BandwidthServiceChange $row, string $amount, ?string $remark = null): array
    {
        return [
            'bandwidth_type_id' => $type->id,
            'label' => $type->name,
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),
            'rate' => $row->rate,
            'mbps' => $type->flat && ! $row->mbps ? null : $row->mbps,
            'amount' => Dec::round($amount, 4),
            'remark' => $remark,
            'sort' => $type->sort * 10 + ($from->day > 1 ? 1 : 0),
        ];
    }
}
