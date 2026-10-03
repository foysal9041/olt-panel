<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ProfitSheet;
use App\Models\TransactionCategory;
use App\Services\ProfitSheetBuilder;
use App\Support\Dec;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * মাসিক লাভ ও শেয়ার বণ্টন — the month's net profit and each partner's part.
 *
 * Draft → lines come from Zone Settlement and the Cash Book and can be
 * corrected; Finalize (admin) → frozen, partners credited, month closed;
 * Reopen (admin) → credits taken back, draft again.
 */
class ProfitSheetController extends Controller
{
    public function __construct(private ProfitSheetBuilder $builder)
    {
    }

    public function index(Request $request)
    {
        $month = $this->month($request->query('month'));
        $saved = ProfitSheet::with(['creator:id,name', 'editor:id,name', 'finalizer:id,name'])->whereDate('month', $month)->first();
        $sheet = $saved ?? new ProfitSheet(['month' => $month, 'status' => 'draft']);

        // A new or reloaded draft is filled in from the data.
        $fresh = ! $saved || ($request->boolean('fresh') && ! $saved->isFinal());
        $suggested = $this->builder->suggest($month);
        if ($fresh) {
            $sheet->lines = $suggested;
        }

        return view('accounts.profit.index', [
            'month' => $month,
            'sheet' => $sheet,
            'saved' => $saved,
            'fresh' => $fresh,
            'calc' => $sheet->calc(),
            'drift' => $sheet->isFinal() ? $this->drift($sheet->lines ?? [], $suggested) : [],
            'months' => ProfitSheet::orderByDesc('month')->get(['id', 'month', 'status']),
            'heads' => TransactionCategory::orderBy('type')->orderBy('name')->get(),
        ]);
    }

    /** Save the draft; with action=finalize (admin) also freeze it and credit the partners. */
    public function save(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'action' => 'nullable|in:save,finalize',
            'lines' => 'array',
            'lines.*.section' => 'required|in:' . implode(',', array_keys(ProfitSheet::SECTIONS)),
            'lines.*.label' => 'nullable|string|max:120',
            'lines.*.amount' => 'nullable|numeric|min:0|max:999999999999',
            'lines.*.source' => 'nullable|string|max:40',
            'notes' => 'nullable|string|max:1000',
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $finalize = ($data['action'] ?? 'save') === 'finalize';

        abort_if($finalize && ! $request->user()->isAdmin(), 403, 'Only an admin can finalize a month.');

        $sheet = ProfitSheet::whereDate('month', $month)->first();
        if ($sheet?->isFinal()) {
            return back()->with('error', "{$month->format('F Y')} is already finalized. An admin has to reopen it to make changes.");
        }

        // Rows left without a name are dropped.
        $lines = collect($data['lines'] ?? [])->filter(fn ($l) => filled($l['label'] ?? null))
            ->map(fn ($l) => ['section' => $l['section'], 'label' => trim($l['label']), 'amount' => Dec::round($l['amount'] ?? 0, 2), 'source' => $l['source'] ?? null])
            ->values()->all();

        $sheet ??= new ProfitSheet(['month' => $month, 'status' => 'draft', 'created_by' => auth()->id()]);
        $sheet->fill(['lines' => $lines, 'notes' => $data['notes'] ?? null, 'updated_by' => auth()->id()])->save();

        $ym = $month->format('Y-m');

        if ($finalize) {
            $people = ProfitSheet::currentPartners();
            if (! $people['shares']) {
                return redirect()->route('accounts.profit.index', ['month' => $ym])->with('error', 'Saved as draft, but not finalized: there are no active shareholders in Partners.');
            }

            $sheet->finalize($request->user());
            $calc = $sheet->calc();
            ActivityLog::record('updated', "Finalized Net Profit {$month->format('M Y')}: ৳" . Dec::lakh($calc['net']) . ' to ' . count($calc['people']) . ' shareholders, commission ৳' . Dec::lakh($calc['commission_total']), $sheet);

            return redirect()->route('accounts.profit.index', ['month' => $ym])
                ->with('success', "{$month->format('F Y')} finalized — Net Profit ৳" . Dec::lakh($calc['net']) . ' credited to the partners. The month is now closed.');
        }

        return redirect()->route('accounts.profit.index', ['month' => $ym])
            ->with('success', "Draft saved — {$month->format('F Y')} Net Profit ৳" . Dec::lakh($sheet->calc()['net']) . '.');
    }

    public function reopen(Request $request, ProfitSheet $profitSheet)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only an admin can reopen a month.');

        $profitSheet->reopen($request->user());
        ActivityLog::record('updated', "Reopened Net Profit {$profitSheet->month->format('M Y')} — partner credits taken back", $profitSheet);

        return redirect()->route('accounts.profit.index', ['month' => $profitSheet->month->format('Y-m')])
            ->with('success', "{$profitSheet->month->format('F Y')} reopened as a draft. The partners' credits for it were taken back; payments stay.");
    }

    public function print(Request $request)
    {
        $month = $this->month($request->query('month'));
        $sheet = ProfitSheet::with('finalizer:id,name')->whereDate('month', $month)->firstOrFail();

        return view('accounts.profit.print', ['sheet' => $sheet, 'calc' => $sheet->calc()]);
    }

    public function destroy(Request $request, ProfitSheet $profitSheet)
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($profitSheet->isFinal()) {
            return back()->with('error', 'A finalized month can\'t be deleted — reopen it first.');
        }

        $profitSheet->delete();

        return redirect()->route('accounts.profit.index', ['month' => $profitSheet->month->format('Y-m')])
            ->with('success', "Deleted the {$profitSheet->month->format('F Y')} draft.");
    }

    /** Where a Cash Book head counts on the sheet. */
    public function head(Request $request, TransactionCategory $category)
    {
        $group = $request->validate(['pl_group' => 'required|in:' . implode(',', array_keys(TransactionCategory::PL_GROUPS[$category->type] ?? []))])['pl_group'];

        $category->update(['pl_group' => $group]);

        return back()->with('success', "“{$category->name}” now counts as: {$category->plGroupLabel()}. Use “Reload from data” on a draft to apply it.");
    }

    /**
     * Data lines whose source has changed since the sheet was finalized
     * (an admin edited a closed month's entries, a settlement was replaced, …).
     *
     * @return list<array{label: string, was: string, now: string}>
     */
    protected function drift(array $saved, array $current): array
    {
        $now = collect($current)->keyBy('source');
        $out = [];

        foreach ($saved as $line) {
            $source = $line['source'] ?? null;
            if (! $source || str_starts_with($source, 'customer:')) {
                continue;
            }
            $amount = $now[$source]['amount'] ?? '0.00';
            if (Dec::round($amount, 2) !== Dec::round($line['amount'], 2)) {
                $out[] = ['label' => $line['label'], 'was' => $line['amount'], 'now' => $amount];
            }
        }

        $known = collect($saved)->pluck('source')->filter()->all();
        foreach ($current as $line) {
            if (! in_array($line['source'], $known, true) && ! str_starts_with($line['source'], 'customer:') && Dec::toFloat($line['amount']) != 0) {
                $out[] = ['label' => $line['label'], 'was' => '0.00', 'now' => $line['amount']];
            }
        }

        return $out;
    }

    /** ?month=Y-m, else last month (the sheet is made after a month closes). */
    protected function month(?string $value): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : Carbon::today()->subMonthNoOverflow()->startOfMonth();
        } catch (\Throwable) {
            return Carbon::today()->subMonthNoOverflow()->startOfMonth();
        }
    }
}
