<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Partner;
use App\Models\PartnerEntry;
use App\Models\Transaction;
use App\Support\Dec;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shareholders and commission holders, each with an account of their own:
 * month-by-month credits from finalized Net Profit sheets, payments made to
 * them and what is still due.
 */
class PartnerController extends Controller
{
    public function index()
    {
        $partners = Partner::ordered()->get();
        $sums = PartnerEntry::query()
            ->selectRaw("partner_id,
                SUM(CASE WHEN type IN ('share','commission') THEN amount ELSE 0 END) AS earned,
                SUM(CASE WHEN type = 'payment' THEN -amount ELSE 0 END) AS paid,
                SUM(CASE WHEN type = 'adjustment' THEN amount ELSE 0 END) AS adjusted,
                SUM(amount) AS balance")
            ->groupBy('partner_id')->get()->keyBy('partner_id');

        return view('accounts.partners.index', compact('partners', 'sums'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only an admin can add partners.');

        $partner = Partner::create($this->validated($request) + ['sort' => (int) Partner::max('sort') + 1]);

        return redirect()->route('accounts.partners.show', $partner)->with('success', "{$partner->name} added.");
    }

    public function update(Request $request, Partner $partner)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only an admin can change shares and commission.');

        $partner->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', "{$partner->name} updated. Shares and commission apply to months not finalized yet.");
    }

    public function show(Partner $partner)
    {
        return view('accounts.partners.show', $this->statement($partner));
    }

    public function print(Partner $partner)
    {
        return view('accounts.partners.print', $this->statement($partner));
    }

    /**
     * Money handed to a partner (or, for an admin, an adjustment). Partners
     * are paid out of the bank, so this never touches the petty cash.
     */
    public function storeEntry(Request $request, Partner $partner)
    {
        $data = $request->validate([
            'type' => 'required|in:payment,adjustment',
            'entry_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|max:999999999',
            'method' => 'nullable|required_if:type,payment|in:' . implode(',', PartnerEntry::METHODS),
            'note' => 'nullable|string|max:255',
        ]);

        $isPayment = $data['type'] === 'payment';
        abort_if(! $isPayment && ! $request->user()->isAdmin(), 403, 'Only an admin can make adjustments.');

        if ($isPayment && (float) $data['amount'] <= 0) {
            return back()->withInput()->withErrors(['amount' => 'A payment must be more than zero.']);
        }
        if (! $isPayment && blank($data['note'] ?? null)) {
            return back()->withInput()->withErrors(['note' => 'Say why the adjustment is made.']);
        }

        $amount = Dec::round($data['amount'], 2);

        $same = PartnerEntry::where('partner_id', $partner->id)->where('type', $data['type'])->whereDate('entry_date', $data['entry_date'])
            ->where('amount', $isPayment ? Dec::sub('0', $amount) : $amount);
        if (\App\Support\DuplicateGuard::recent($same)) {
            return back()->withInput()->with('error', \App\Support\DuplicateGuard::message());
        }

        PartnerEntry::create([
            'partner_id' => $partner->id,
            'entry_date' => $data['entry_date'],
            'type' => $data['type'],
            'amount' => $isPayment ? Dec::sub('0', $amount) : $amount,
            'method' => $isPayment ? $data['method'] : null,
            'note' => $data['note'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        return back()->with('success', $isPayment ? "Payment of ৳" . Dec::lakh($amount) . " to {$partner->name} recorded." : 'Adjustment recorded.');
    }

    public function destroyEntry(Request $request, Partner $partner, PartnerEntry $entry)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only an admin can remove entries.');
        abort_unless($entry->partner_id === $partner->id, 404);

        if ($entry->isFromSheet()) {
            return back()->with('error', 'This credit comes from a finalized Net Profit month — reopen that month instead.');
        }
        if ($entry->transaction && ! Transaction::dayIsOpenFor($entry->transaction->transaction_date, $request->user())) {
            return back()->with('error', Transaction::lockReason($entry->transaction->transaction_date) . ' (its Cash Book entry is in that month).');
        }

        DB::transaction(function () use ($entry) {
            $entry->transaction?->delete();
            $entry->delete();
        });
        ActivityLog::record('deleted', "Removed {$entry->typeLabel()} ৳" . Dec::lakh(ltrim($entry->amount, '-')) . " for {$partner->name}", $partner);

        return back()->with('success', 'Entry removed.');
    }

    /** Entries oldest first with a running balance, plus totals. */
    protected function statement(Partner $partner): array
    {
        $balance = '0';
        $earned = '0';
        $paid = '0';
        $rows = $partner->entries()->with(['sheet:id,month,status', 'recorder:id,name', 'transaction:id'])->get()
            ->map(function (PartnerEntry $e) use (&$balance, &$earned, &$paid) {
                $balance = Dec::add($balance, $e->amount);
                if (in_array($e->type, ['share', 'commission'], true)) {
                    $earned = Dec::add($earned, $e->amount);
                } elseif ($e->type === 'payment') {
                    $paid = Dec::sub($paid, $e->amount);
                }
                $e->running = $balance;

                return $e;
            });

        return ['partner' => $partner, 'rows' => $rows, 'earned' => $earned, 'paid' => $paid, 'balance' => $balance];
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'phone' => 'nullable|string|max:30',
            'share' => 'nullable|numeric|min:0|max:100000',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $data['share'] = $data['share'] ?? 0;
        $data['commission_percent'] = $data['commission_percent'] ?? 0;

        return $data;
    }
}
