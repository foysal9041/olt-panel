<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrLetter;
use App\Services\HrDocs;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Appointment and termination letters on the company pad. Pick the
 * employee and the details fill in from their record; every letter is kept
 * (with its own reference number) so it can be printed again.
 */
class HrLetterController extends Controller
{
    /** Terms every appointment letter starts with (editable per letter). */
    public const DEFAULT_TERMS = [
        'You will follow the company\'s rules, policies and code of conduct, as amended from time to time.',
        'You will keep all company, customer and network information confidential, during and after your employment.',
        'Tools, devices, SIM cards and the office ID card given to you remain company property and must be returned when you leave.',
        'You may be asked to work at any POP, zone or office of the company, and outside office hours when the network needs it.',
        'Leave is as per the company leave policy; apply for leave from your dashboard.',
    ];

    public function __construct(private HrDocs $hr)
    {
    }

    public function index(Request $request)
    {
        $letters = HrLetter::with(['employee', 'creator'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('employee'), fn ($q) => $q->where('employee_id', $request->employee))
            ->latest('letter_date')->latest('id')->paginate(30)->withQueryString();

        return view('attendance.letters.index', [
            'letters' => $letters,
            'employees' => Employee::orderBy('name')->get(['id', 'name']),
            'counts' => HrLetter::selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type'),
        ]);
    }

    public function create(Request $request)
    {
        $type = array_key_exists($request->query('type'), HrLetter::TYPES) ? $request->query('type') : 'appointment';
        $employee = $request->filled('employee') ? Employee::find($request->integer('employee')) : null;

        return view('attendance.letters.form', [
            'letter' => new HrLetter(['type' => $type, 'letter_date' => today(), 'employee_id' => $employee?->id,
                'ref_no' => HrLetter::nextRef($type, today()), 'data' => $this->defaults($type, $employee)]),
        ] + $this->formData());
    }

    public function store(Request $request)
    {
        $type = $request->validate(['type' => ['required', Rule::in(array_keys(HrLetter::TYPES))]])['type'];
        [$fields, $data] = $this->validated($request, $type);

        $letter = DB::transaction(function () use ($type, $fields, $data, $request) {
            $date = Carbon::parse($fields['letter_date']);
            $letter = HrLetter::create([
                'type' => $type,
                'employee_id' => $fields['employee_id'] ?? null,
                'letter_date' => $date,
                'ref_no' => HrLetter::nextRef($type, $date),
                'data' => $data,
                'created_by' => $request->user()->id,
            ]);
            $this->applyToEmployee($letter, $request);

            return $letter;
        });

        return redirect()->route('attendance.letters.show', $letter)->with('success', "{$letter->typeLabel()} {$letter->ref_no} made.");
    }

    public function show(HrLetter $letter)
    {
        return view('attendance.letters.print', [
            'letter' => $letter,
            'd' => $letter->data,
            'signature' => $this->hr->signatureDataUrl(),
        ]);
    }

    public function edit(HrLetter $letter)
    {
        return view('attendance.letters.form', ['letter' => $letter] + $this->formData());
    }

    public function update(Request $request, HrLetter $letter)
    {
        [$fields, $data] = $this->validated($request, $letter->type);
        $letter->update(['employee_id' => $fields['employee_id'] ?? null, 'letter_date' => $fields['letter_date'], 'data' => $data]);
        $this->applyToEmployee($letter, $request);

        return redirect()->route('attendance.letters.show', $letter)->with('success', "{$letter->ref_no} saved.");
    }

    public function destroy(Request $request, HrLetter $letter)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $letter->delete();

        return redirect()->route('attendance.letters.index')->with('success', "{$letter->ref_no} removed.");
    }

    /** What a new letter starts with, from the employee's record. */
    protected function defaults(string $type, ?Employee $e): array
    {
        $s = $this->hr->settings();
        $common = [
            'name' => $e?->name, 'emp_id' => $e?->empId(), 'designation' => $e?->designation, 'department' => $e?->department,
            'address' => $e?->address, 'phone' => $e?->phone,
            'signatory' => $s['signatory'], 'signatory_title' => $s['signatory_title'],
        ];

        if ($type === 'appointment') {
            return $common + [
                'joining_date' => ($e?->joining_date ?? today())->toDateString(),
                'salary_basic' => $e?->basic_salary ? (float) $e->basic_salary : null,
                'salary_house_rent' => $e?->house_rent ? (float) $e->house_rent : null,
                'salary_other' => null,
                'probation_months' => 3,
                'working_hours' => '9:00 AM to 6:00 PM',
                'weekly_off' => $e?->weekly_off_day ?? 'Friday',
                'reporting_to' => null,
                'workplace' => 'Sunlit Network DC, ' . $s['address'],
                'notice_days' => 30,
                'terms' => implode("\n", self::DEFAULT_TERMS),
            ];
        }

        return $common + [
            'joining_date' => $e?->joining_date?->toDateString(),
            'reason' => 'resignation',
            'reason_details' => null,
            'resignation_date' => null,
            'effective_date' => today()->toDateString(),
            'notice_pay' => false,
            'settlement' => 'Your salary up to the last working day and any other dues will be paid within 30 days, after all company property is returned and your accounts are cleared.',
            'property' => 'Office ID card, tools and equipment, laptop / mobile / SIM, keys and any company documents.',
        ];
    }

    /** @return array{0: array, 1: array} the letter's own columns, and its data */
    protected function validated(Request $request, string $type): array
    {
        $rules = [
            'employee_id' => 'nullable|exists:employees,id',
            'letter_date' => 'required|date',
            'name' => 'required|string|max:255',
            'emp_id' => 'nullable|string|max:30',
            'designation' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'signatory' => 'required|string|max:100',
            'signatory_title' => 'required|string|max:100',
        ];
        $rules += $type === 'appointment' ? [
            'joining_date' => 'required|date',
            'salary_basic' => 'required|numeric|min:0',
            'salary_house_rent' => 'nullable|numeric|min:0',
            'salary_other' => 'nullable|numeric|min:0',
            'probation_months' => 'nullable|integer|min:0|max:24',
            'working_hours' => 'nullable|string|max:100',
            'weekly_off' => 'nullable|string|max:50',
            'reporting_to' => 'nullable|string|max:255',
            'workplace' => 'nullable|string|max:255',
            'notice_days' => 'nullable|integer|min:0|max:180',
            'terms' => 'nullable|string|max:5000',
        ] : [
            'joining_date' => 'nullable|date',
            'reason' => ['required', Rule::in(array_keys(HrLetter::REASONS))],
            'reason_details' => 'nullable|string|max:2000',
            'resignation_date' => 'nullable|date',
            'effective_date' => 'required|date',
            'notice_pay' => 'nullable|boolean',
            'settlement' => 'nullable|string|max:2000',
            'property' => 'nullable|string|max:1000',
        ];

        $all = $request->validate($rules);
        $fields = ['employee_id' => $all['employee_id'] ?? null, 'letter_date' => $all['letter_date']];
        $data = collect($all)->except(['employee_id', 'letter_date'])->all();
        if ($type === 'termination') {
            $data['notice_pay'] = $request->boolean('notice_pay');
        }

        return [$fields, $data];
    }

    /**
     * Appointment: keep the joining date / designation / salary on the
     * employee. Termination: optionally mark them as left on the last day.
     */
    protected function applyToEmployee(HrLetter $letter, Request $request): void
    {
        $e = $letter->employee;
        if (! $e) {
            return;
        }
        $d = $letter->data;

        if ($letter->type === 'appointment' && $request->boolean('update_employee')) {
            $e->update(array_filter([
                'designation' => $d['designation'],
                'department' => $d['department'] ?? null,
                'joining_date' => $d['joining_date'],
                'basic_salary' => $d['salary_basic'],
                'house_rent' => $d['salary_house_rent'] ?? null,
                'address' => $d['address'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''));
        }

        if ($letter->type === 'termination' && $request->boolean('mark_left')) {
            // Past days take effect now; a future last day is handled by the nightly "employees-left" job.
            $gone = Carbon::parse($d['effective_date'])->lt(today());
            $e->update(['left_on' => $d['effective_date'], 'status' => ! $gone]);
            if ($gone) {
                \App\Models\User::where('employee_id', $e->id)->where('status', 1)->update(['status' => 0]);
            }
        }
    }

    protected function formData(): array
    {
        return [
            'employees' => Employee::orderByDesc('status')->orderBy('name')->get(),
            'reasons' => HrLetter::REASONS,
        ];
    }
}
