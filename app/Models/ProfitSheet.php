<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One month's net profit statement and how it is shared out:
 *
 *   Commissionable income = Bill income − Fixed cost
 *   Commission            = each holder's % of that (Shawon 12.5 %, Tusher 7.5 %)
 *   Net profit            = (Bill + Others income) − (Fixed + Others cost + Commission)
 *   Per share             = Net profit ÷ total shares; each partner gets share × per share
 *
 * A draft takes its commission holders and shareholders from Partners as
 * they are now. Finalizing freezes everything (lines and who got what),
 * credits each partner's account and closes the month: the Cash Book,
 * Income & Expenses and Zone Settlement can no longer change it until an
 * admin reopens the month.
 */
class ProfitSheet extends Model
{
    use LogsActivity;

    public const SECTIONS = [
        'bill' => 'Bill Income (Cash IN)',
        'other_income' => 'Others Income',
        'fixed_cost' => 'Fixed Cost',
        'other_cost' => 'Others Cost',
    ];

    protected $fillable = ['month', 'status', 'lines', 'commission', 'shares', 'notes', 'finalized_at', 'finalized_by', 'created_by', 'updated_by'];

    protected $casts = [
        'month' => 'date',
        'lines' => 'array',
        'commission' => 'array',
        'shares' => 'array',
        'finalized_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function entries()
    {
        return $this->hasMany(PartnerEntry::class);
    }

    public function isFinal(): bool
    {
        return $this->status === 'final';
    }

    /** Is the month of $date closed (its sheet finalized)? */
    public static function isMonthClosed(\DateTimeInterface|string|null $date): bool
    {
        if (! $date) {
            return false;
        }

        return self::where('status', 'final')
            ->whereDate('month', Carbon::parse($date)->startOfMonth()->toDateString())
            ->exists();
    }

    /**
     * Commission holders and shareholders as Partners has them now.
     *
     * @return array{commission: list<array{partner_id:int, name:string, percent:string}>, shares: list<array{partner_id:int, name:string, share:string}>}
     */
    public static function currentPartners(): array
    {
        $partners = Partner::active()->ordered()->get();

        return [
            'commission' => $partners->filter->isCommissionHolder()
                ->map(fn (Partner $p) => ['partner_id' => $p->id, 'name' => $p->name, 'percent' => (string) $p->commission_percent])->values()->all(),
            'shares' => $partners->filter->isShareholder()
                ->map(fn (Partner $p) => ['partner_id' => $p->id, 'name' => $p->name, 'share' => (string) $p->share])->values()->all(),
        ];
    }

    /** Every figure on the sheet; a draft uses today's partners, a final sheet its frozen ones. */
    public function calc(): array
    {
        $people = $this->isFinal()
            ? ['commission' => $this->commission ?? [], 'shares' => $this->shares ?? []]
            : self::currentPartners();

        return self::compute($this->lines ?? [], $people['commission'], $people['shares']);
    }

    /**
     * @return array{sections: array<string, string>, commission_base: string, commission: list<array>, commission_total: string,
     *               other_cost: string, income: string, cost: string, net: string, shares_total: string, per_share: string, people: list<array>}
     */
    public static function compute(array $lines, array $commission, array $shares): array
    {
        $sections = array_fill_keys(array_keys(self::SECTIONS), '0');
        foreach ($lines as $line) {
            if (isset($sections[$line['section'] ?? null])) {
                $sections[$line['section']] = Dec::add($sections[$line['section']], Dec::round($line['amount'] ?? 0, 2));
            }
        }

        // Commission is paid on what the bills bring in after running costs.
        $base = Dec::sub($sections['bill'], $sections['fixed_cost']);
        $base = Dec::isNegative($base) ? '0' : $base;

        $holders = [];
        $commissionTotal = '0';
        foreach ($commission as $c) {
            $amount = Dec::round(Dec::mul($base, Dec::div($c['percent'] ?? 0, 100)), 2);
            $holders[] = ['partner_id' => $c['partner_id'] ?? null, 'name' => (string) ($c['name'] ?? ''), 'percent' => (string) ($c['percent'] ?? '0'), 'amount' => $amount];
            $commissionTotal = Dec::add($commissionTotal, $amount);
        }

        $otherCost = Dec::add($sections['other_cost'], $commissionTotal);
        $income = Dec::add($sections['bill'], $sections['other_income']);
        $cost = Dec::add($sections['fixed_cost'], $otherCost);
        $net = Dec::sub($income, $cost);

        $sharesTotal = '0';
        foreach ($shares as $s) {
            $sharesTotal = Dec::add($sharesTotal, $s['share'] ?? 0);
        }

        $perShare = Dec::toFloat($sharesTotal) > 0 ? Dec::div($net, $sharesTotal) : '0';
        $people = [];
        foreach ($shares as $s) {
            $people[] = [
                'partner_id' => $s['partner_id'] ?? null,
                'name' => (string) ($s['name'] ?? ''),
                'share' => (string) ($s['share'] ?? '0'),
                'amount' => Dec::round(Dec::mul($perShare, $s['share'] ?? 0), 2),
            ];
        }

        return [
            'sections' => $sections,
            'commission_base' => $base,
            'commission' => $holders,
            'commission_total' => $commissionTotal,
            'other_cost' => $otherCost,
            'income' => $income,
            'cost' => $cost,
            'net' => $net,
            'shares_total' => $sharesTotal,
            'per_share' => $perShare,
            'people' => $people,
        ];
    }

    /**
     * Freeze the sheet with today's partners, credit each of them and close
     * the month. Safe against double clicks (row lock + status check).
     */
    public function finalize(User $by): void
    {
        DB::transaction(function () use ($by) {
            $sheet = self::whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            if ($sheet->isFinal()) {
                return;
            }

            $people = self::currentPartners();
            $sheet->commission = $people['commission'];
            $sheet->shares = $people['shares'];
            $calc = self::compute($sheet->lines ?? [], $sheet->commission, $sheet->shares);

            $date = $sheet->month->copy()->endOfMonth()->toDateString();
            $label = $sheet->month->format('M Y');

            foreach ($calc['commission'] as $c) {
                PartnerEntry::create([
                    'partner_id' => $c['partner_id'], 'profit_sheet_id' => $sheet->id, 'entry_date' => $date, 'type' => 'commission',
                    'amount' => $c['amount'], 'recorded_by' => $by->id,
                    'note' => "Commission {$label} — " . rtrim(rtrim($c['percent'], '0'), '.') . '% of ৳' . Dec::lakh($calc['commission_base']),
                ]);
            }
            foreach ($calc['people'] as $p) {
                PartnerEntry::create([
                    'partner_id' => $p['partner_id'], 'profit_sheet_id' => $sheet->id, 'entry_date' => $date, 'type' => 'share',
                    'amount' => $p['amount'], 'recorded_by' => $by->id,
                    'note' => "Net profit {$label} — " . rtrim(rtrim($p['share'], '0'), '.') . ' shares × ৳' . Dec::lakh($calc['per_share']),
                ]);
            }

            $sheet->forceFill(['status' => 'final', 'finalized_at' => now(), 'finalized_by' => $by->id, 'updated_by' => $by->id])->save();
            $this->setRawAttributes($sheet->getAttributes(), true);
        });
    }

    /** Take back the month's credits (payments stay) and reopen it as a draft. */
    public function reopen(User $by): void
    {
        DB::transaction(function () use ($by) {
            $sheet = self::whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            if (! $sheet->isFinal()) {
                return;
            }

            $sheet->entries()->whereIn('type', ['share', 'commission'])->get()->each->delete();
            $sheet->forceFill(['status' => 'draft', 'finalized_at' => null, 'finalized_by' => null, 'updated_by' => $by->id])->save();
            $this->setRawAttributes($sheet->getAttributes(), true);
        });
    }

    protected function activityLogLabel(): string
    {
        return 'Profit Sheet';
    }

    protected function activityLogTitle(): string
    {
        return ($this->month?->format('M Y') ?? '#' . $this->getKey()) . ($this->isFinal() ? ' (final)' : ' (draft)');
    }

    protected function activityLogExcept(): array
    {
        return ['lines', 'commission', 'shares', 'created_at', 'updated_at'];
    }
}
