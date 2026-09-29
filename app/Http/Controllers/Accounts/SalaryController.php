<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalarySheet;
use App\Models\SalarySheetItem;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * বেতন শিট — monthly salary sheet (Salary, House Rent, Eid Bonus, Advance,
 * Deduction, Net Pay). Salary is postpaid: a month's salary is paid in the
 * following month, so posting records each Net Pay as a বেতন expense on a
 * pay date in the next month (August's salary lands in September's books).
 * A posted sheet is locked; "Undo posting" removes its বেতন expenses so it
 * can be corrected and posted again.
 */
class SalaryController extends Controller
{
    public function index(Request $request)
    {
        $month = $this->month($request->query('month'));
        $sheet = SalarySheet::with('items')->where('month', $month->toDateString())->first();

        if ($sheet) {
            $rows = $sheet->items;
        } else {
            // New month: start from last month's sheet, else employee defaults.
            $previous = SalarySheet::with('items')->where('month', '<', $month->toDateString())->orderByDesc('month')->first();

            $rows = $previous
                ? $previous->items->map(fn ($i) => new SalarySheetItem($i->only(['employee_id', 'emp_code', 'name', 'designation', 'salary', 'house_rent'])))
                : Employee::where('status', true)->orderBy('emp_code')->orderBy('name')->get()
                    ->map(fn (Employee $e) => new SalarySheetItem([
                        'employee_id' => $e->id,
                        'emp_code' => $e->emp_code,
                        'name' => $e->name,
                        'designation' => $e->designation,
                        'salary' => (float) $e->basic_salary,
                        'house_rent' => (float) $e->house_rent,
                    ]));
        }

        $employees = Employee::orderBy('name')->get(['id', 'emp_code', 'name', 'designation', 'basic_salary', 'house_rent']);
        $payDate = $sheet?->items->first()?->transaction?->transaction_date ?? $this->defaultPayDate($month);
        $payMonth = $month->copy()->addMonthNoOverflow();

        $view = $request->boolean('print') ? 'accounts.salaries.print' : 'accounts.salaries.index';

        return view($view, compact('month', 'sheet', 'rows', 'employees', 'payDate', 'payMonth'));
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
            'pay_date' => 'required|date',
            'post' => 'nullable|boolean',
            'rows' => 'array',
            'rows.*.employee_id' => 'nullable|exists:employees,id',
            'rows.*.emp_code' => 'nullable|string|max:30',
            'rows.*.name' => 'required|string|max:255',
            'rows.*.designation' => 'nullable|string|max:255',
            'rows.*.salary' => 'nullable|numeric|min:0',
            'rows.*.house_rent' => 'nullable|numeric|min:0',
            'rows.*.bonus' => 'nullable|numeric|min:0',
            'rows.*.advance' => 'nullable|numeric|min:0',
            'rows.*.deduction' => 'nullable|numeric|min:0',
        ], [
            'rows.*.name.required' => 'Every row needs a name.',
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();

        // Postpaid: the pay date must fall in a later month than the salary month.
        if (Carbon::parse($validated['pay_date'])->lt($month->copy()->addMonthNoOverflow()->startOfMonth())) {
            return back()->withInput()->withErrors([
                'pay_date' => 'Salary is paid the following month — the pay date must be on or after '
                    . $month->copy()->addMonthNoOverflow()->startOfMonth()->format('d M Y') . '.',
            ]);
        }

        // Salary is posted once a month; a posted sheet is final until an
        // admin undoes the posting.
        if (SalarySheet::where('month', $month->toDateString())->whereNotNull('posted_at')->exists()) {
            return redirect()
                ->route('accounts.salaries.index', ['month' => $month->format('Y-m')])
                ->with('error', 'This month\'s salary is already posted and can\'t be changed. Use "Undo posting" first.');
        }

        DB::transaction(function () use ($validated, $month) {
            $sheet = SalarySheet::firstOrCreate(['month' => $month->toDateString()]);
            $post = (bool) ($validated['post'] ?? false);

            // Replace the lines; their বেতন expenses are rebuilt below.
            $this->removeExpenses($sheet);
            $sheet->items()->delete();

            foreach (array_values($validated['rows'] ?? []) as $i => $row) {
                $sheet->items()->create([
                    'employee_id' => $row['employee_id'] ?? null,
                    'emp_code' => $row['emp_code'] ?? null,
                    'name' => $row['name'],
                    'designation' => $row['designation'] ?? null,
                    'salary' => (float) ($row['salary'] ?? 0),
                    'house_rent' => (float) ($row['house_rent'] ?? 0),
                    'bonus' => (float) ($row['bonus'] ?? 0),
                    'advance' => (float) ($row['advance'] ?? 0),
                    'deduction' => (float) ($row['deduction'] ?? 0),
                    'sort' => $i,
                ]);
            }

            if ($post) {
                $this->createExpenses($sheet->fresh('items'), Carbon::parse($validated['pay_date']));
                $sheet->posted_at ??= now();
            } else {
                $sheet->posted_at = null;
            }

            $sheet->save();
        });

        $sheet = SalarySheet::where('month', $month->toDateString())->first();

        return redirect()
            ->route('accounts.salaries.index', ['month' => $month->format('Y-m')])
            ->with('success', $sheet->isPosted()
                ? 'Salary sheet saved and posted — Net Pay is recorded under বেতন.'
                : 'Salary sheet saved (not posted to expenses yet).');
    }

    public function unpost(Request $request)
    {
        $month = $this->month($request->input('month'));
        $sheet = SalarySheet::where('month', $month->toDateString())->firstOrFail();

        DB::transaction(function () use ($sheet) {
            $this->removeExpenses($sheet);
            $sheet->update(['posted_at' => null]);
        });

        return back()->with('success', 'Posting undone — the বেতন expenses for this month were removed.');
    }

    protected function createExpenses(SalarySheet $sheet, Carbon $payDate): void
    {
        $category = TransactionCategory::firstOrCreate(['name' => 'বেতন', 'type' => 'expense']);

        foreach ($sheet->items as $item) {
            if ($item->net <= 0) {
                continue;
            }

            $transaction = Transaction::create([
                'transaction_category_id' => $category->id,
                'amount' => $item->net,
                'description' => "বেতন {$sheet->month->format('M Y')} — {$item->name}",
                'transaction_date' => $payDate->toDateString(),
                'recorded_by' => auth()->id(),
                'zone' => auth()->user()?->zone,
            ]);

            $item->update(['transaction_id' => $transaction->id]);
        }
    }

    protected function removeExpenses(SalarySheet $sheet): void
    {
        $ids = $sheet->items()->whereNotNull('transaction_id')->pluck('transaction_id');
        $sheet->items()->update(['transaction_id' => null]);
        Transaction::whereIn('id', $ids)->delete();
    }

    /**
     * Today if we're already in (or past) the pay month, else the 1st of it.
     */
    protected function defaultPayDate(Carbon $month): Carbon
    {
        $payStart = $month->copy()->addMonthNoOverflow()->startOfMonth();

        return Carbon::today()->gte($payStart) && Carbon::today()->lte($payStart->copy()->endOfMonth())
            ? Carbon::today()
            : $payStart;
    }

    protected function month(?string $value): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : Carbon::today()->startOfMonth();
        } catch (\Throwable) {
            return Carbon::today()->startOfMonth();
        }
    }
}
