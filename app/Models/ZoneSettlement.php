<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;

/**
 * One month's zone / partner settlement imported from Excel.
 */
class ZoneSettlement extends Model
{
    use LogsActivity;

    protected $fillable = [
        'month', 'cycle', 'invoice_date', 'bkash_percent', 'source_name', 'source_path', 'sheet_name',
        'mapping', 'original', 'blank_deduction_as_zero', 'notes', 'created_by', 'prepared_by', 'prepared_title',
    ];

    protected $casts = [
        'month' => 'date',
        'invoice_date' => 'date',
        'bkash_percent' => 'string',
        'mapping' => 'array',
        'original' => 'array',
        'blank_deduction_as_zero' => 'boolean',
    ];

    protected $hidden = ['original'];

    /**
     * Billing cycles. Each has its own Excel; a month's bill covers from
     * `start` of the previous month to the day before `start` of this one
     * (start 1 = the calendar month). `line` is the name on the profit sheet.
     */
    public const CYCLES = [
        'fixed' => ['label' => 'Fixed Date Paid', 'start' => 11, 'line' => 'All Fix Date Paid'],
        'balunda' => ['label' => 'Balunda', 'start' => 16, 'line' => 'Balunda Tahazzat'],
        'nttn' => ['label' => 'NTTN Client', 'start' => 1, 'line' => 'NTTN Client Bill'],
    ];

    public function cycleLabel(): string
    {
        return self::CYCLES[$this->cycle]['label'] ?? self::CYCLES['fixed']['label'];
    }

    /**
     * First and last day billed, e.g. Sep 2026 fixed → 11 Aug – 10 Sep.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    public function period(): array
    {
        $start = self::CYCLES[$this->cycle]['start'] ?? 11;
        $month = $this->month->copy()->startOfMonth();

        if ($start === 1) {
            return [$month, $month->copy()->endOfMonth()];
        }

        return [$month->copy()->subMonthNoOverflow()->day($start), $month->copy()->day($start - 1)];
    }

    public function periodLabel(string $format = 'd M Y'): string
    {
        [$from, $to] = $this->period();

        return $from->format($format) . ' – ' . $to->format($format);
    }

    public function rows()
    {
        return $this->hasMany(ZoneSettlementRow::class)->orderBy('source_row');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Its month's Net Profit sheet is finalized: the settlement is part of closed books. */
    public function isClosed(): bool
    {
        return ProfitSheet::isMonthClosed($this->month);
    }

    /**
     * Grand totals over the counted rows, plus the count.
     *
     * @return array{count:int, payment:string, deduction:string, invoice:string, difference:string, bkash:string, income:string}
     */
    public function totals(): array
    {
        $t = ['count' => 0, 'payment' => '0', 'deduction' => '0', 'invoice' => '0', 'difference' => '0', 'bkash' => '0', 'income' => '0'];

        foreach ($this->rows->where('included', true) as $row) {
            $c = $row->calc($this->bkash_percent);
            $t['count']++;
            foreach (['payment', 'deduction', 'invoice', 'difference', 'bkash', 'income'] as $k) {
                $t[$k] = Dec::add($t[$k], $c[$k]);
            }
        }

        return $t;
    }

    protected function activityLogLabel(): string
    {
        return 'Zone Settlement';
    }

    protected function activityLogTitle(): string
    {
        return ($this->month?->format('M Y') ?? '') . ' · ' . $this->cycleLabel() . ' · ' . $this->source_name;
    }

    protected function activityLogExcept(): array
    {
        return ['original', 'mapping', 'created_at', 'updated_at'];
    }
}
