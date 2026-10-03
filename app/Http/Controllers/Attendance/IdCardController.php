<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Employee;
use App\Models\IdCard;
use App\Services\HrDocs;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Office ID cards (CR80, 85.6 × 54 mm) for anyone: an employee (details
 * filled from their record) or someone who isn't. Each saved card has its
 * own number, issue and expiry date; the QR on the back opens a public page
 * that says whether that card is still valid.
 */
class IdCardController extends Controller
{
    public function __construct(private HrDocs $hr)
    {
    }

    public function index(Request $request)
    {
        $cards = IdCard::with('employee')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$request->q}%")
                ->orWhere('card_no', 'like', "%{$request->q}%")->orWhere('id_no', 'like', "%{$request->q}%")))
            ->latest('id')->paginate(30)->withQueryString();

        return view('attendance.idcards.index', [
            'cards' => $cards,
            'employees' => Employee::with(['idCards' => fn ($q) => $q->latest('id')])->orderByDesc('status')->orderBy('name')->get(),
            'settings' => $this->hr->settings(),
            'themes' => HrDocs::THEMES,
            'signature' => $this->hr->signatureDataUrl(),
        ]);
    }

    /** New card (blank, or from an employee) or an existing card (?card=). */
    public function make(Request $request)
    {
        $settings = $this->hr->settings();

        if ($request->filled('card')) {
            $card = IdCard::with('employee')->findOrFail($request->integer('card'));
            $photo = $this->hr->fileDataUrl($card->photo);
        } else {
            $employee = $request->filled('employee') ? Employee::findOrFail($request->integer('employee')) : null;
            $card = new IdCard([
                'employee_id' => $employee?->id,
                'name' => $employee?->name,
                'designation' => $employee?->designation,
                'department' => $employee?->department,
                'id_no' => $employee?->empId(),
                'blood_group' => $employee?->blood_group,
                'phone' => $employee?->phone,
                'joining_date' => $employee?->joining_date,
                'issue_date' => today(),
                'expiry_date' => today()->addYears((int) ($settings['validity_years'] ?: 2))->subDay(),
                'theme' => $settings['theme'],
            ]);
            $card->setRelation('employee', $employee);
            $photo = $employee ? $this->hr->photoDataUrl($employee) : null;
        }

        return view('attendance.idcards.make', [
            'card' => $card,
            'employees' => Employee::orderByDesc('status')->orderBy('name')->get(['id', 'name', 'designation', 'status']),
            'settings' => $settings,
            'themes' => HrDocs::THEMES,
            'photo' => $photo,
            'signature' => $this->hr->signatureDataUrl('white'),
            'earlier' => $card->employee_id
                ? IdCard::where('employee_id', $card->employee_id)->whereKeyNot($card->id ?? 0)->latest('id')->get()
                : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $card = DB::transaction(function () use ($data, $request) {
            $card = new IdCard($data['card'] + ['card_no' => IdCard::nextNumber(), 'created_by' => $request->user()->id]);
            $card->photo = $this->photoFor($card, $data['photo']);
            $card->save();
            $this->toEmployee($card, $request);

            return $card;
        });

        return redirect()->route('attendance.idcards.make', ['card' => $card->id])
            ->with('success', "Card {$card->card_no} saved for {$card->name} — download or print it now.");
    }

    public function update(Request $request, IdCard $card)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($card, $data, $request) {
            $card->fill($data['card']);
            if ($data['photo']) {
                $card->photo = $this->hr->savePhoto($data['photo'], $card->photo);
            }
            $card->save();
            $this->toEmployee($card, $request);
        });

        return redirect()->route('attendance.idcards.make', ['card' => $card->id])->with('success', "Card {$card->card_no} saved.");
    }

    /** Several saved cards on A4 landscape pages, 5 a page, with cut lines. */
    public function sheet(Request $request)
    {
        $ids = collect((array) $request->query('cards'))->map(fn ($id) => (int) $id)->filter()->unique()->values();
        abort_if($ids->isEmpty(), 404);
        $cards = IdCard::with('employee')->whereIn('id', $ids)->get()->sortBy(fn ($c) => $ids->search($c->id))->values();
        $settings = $this->hr->settings();

        return view('attendance.idcards.print-sheet', [
            'items' => $cards->map(fn (IdCard $card) => [
                'c' => [
                    'name' => $card->name, 'designation' => $card->designation, 'department' => $card->department,
                    'id_label' => $card->employee_id ? 'Emp ID' : 'ID No', 'id_no' => $card->id_no,
                    'joining_date' => $card->joining_date?->format('d M Y'), 'blood_group' => $card->blood_group, 'phone' => $card->phone,
                    'expiry' => $card->expiry_date?->format('d M Y'), 'card_no' => $card->card_no,
                    'photo' => $this->hr->fileDataUrl($card->photo), 'qr' => $card->verifyUrl(),
                ],
                't' => HrDocs::THEMES[$card->theme ?: $settings['theme']] ?? HrDocs::THEMES['navy'],
                'valid' => $card->isValid(),
            ]),
            'settings' => $settings,
            'signature' => $this->hr->signatureDataUrl('white'),
        ]);
    }

    /** Lost or returned: the QR then shows the card as not valid. */
    public function revoke(IdCard $card)
    {
        $card->update(['revoked_at' => $card->revoked_at ? null : now()]);

        return back()->with('success', $card->revoked_at ? "Card {$card->card_no} cancelled — its QR now shows NOT VALID." : "Card {$card->card_no} is valid again.");
    }

    public function destroy(Request $request, IdCard $card)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $card->delete();
        if ($card->photo && ! IdCard::where('photo', $card->photo)->exists() && ! Employee::where('photo', $card->photo)->exists()) {
            Storage::disk('local')->delete($card->photo);
        }

        return redirect()->route('attendance.idcards.index')->with('success', "Card {$card->card_no} removed.");
    }

    /** The back side, signature and validity, shared by every card (and the letters' signatory). */
    public function settings(Request $request)
    {
        $data = $request->validate([
            'phone' => 'nullable|string|max:50',
            'office_phone' => 'nullable|string|max:50',
            'email' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'theme' => ['required', Rule::in(array_keys(HrDocs::THEMES))],
            'validity_years' => 'nullable|integer|min:1|max:10',
            'signatory' => 'nullable|string|max:100',
            'signatory_title' => 'nullable|string|max:100',
            'signature_file' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'signature_data' => 'nullable|string|max:3000000',
            'remove_signature' => 'nullable|boolean',
        ]);

        $current = $this->hr->settings();
        $signature = $current['signature'];
        if ($request->hasFile('signature_file')) {
            $signature = $this->hr->storeSignature($request->file('signature_file'));
        } elseif (filled($data['signature_data'] ?? null)) {
            $signature = $this->hr->storeSignature($data['signature_data']);
        } elseif ($request->boolean('remove_signature')) {
            $signature = null;
        }
        if ($signature !== $current['signature']) {
            $this->hr->deleteSignature($current['signature']);
        }

        unset($data['signature_file'], $data['signature_data'], $data['remove_signature']);
        $data['validity_years'] = (int) ($data['validity_years'] ?? 2);
        AppSetting::put('hr', array_merge($current, $data, ['signature' => $signature]));
        \App\Models\ActivityLog::record('updated', 'Changed the ID card / letter settings');

        return back()->with('success', $signature !== $current['signature'] ? 'Signature saved.' : 'ID card settings saved.');
    }

    /** Just the signature: drawn on the page or uploaded. */
    public function signature(Request $request)
    {
        $request->validate([
            'signature_file' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'signature_data' => 'nullable|string|max:3000000',
            'remove_signature' => 'nullable|boolean',
        ]);
        $settings = $this->hr->settings();
        $old = $settings['signature'];

        if ($request->hasFile('signature_file')) {
            $settings['signature'] = $this->hr->storeSignature($request->file('signature_file'));
        } elseif ($request->filled('signature_data')) {
            $settings['signature'] = $this->hr->storeSignature($request->input('signature_data'));
        } elseif ($request->boolean('remove_signature')) {
            $settings['signature'] = null;
        } else {
            return back()->with('error', 'Draw the signature or choose a picture of it first.');
        }

        $this->hr->deleteSignature($old);
        AppSetting::put('hr', $settings);
        \App\Models\ActivityLog::record('updated', $settings['signature'] ? 'Changed the authorized signature for ID cards and letters' : 'Removed the authorized signature');

        return back()->with('success', $settings['signature'] ? 'Signature saved — it is on the cards and letters now.' : 'Signature removed.');
    }

    /** An employee's photo, for signed-in pages. */
    public function photo(Employee $employee)
    {
        abort_unless($employee->photo && Storage::disk('local')->exists($employee->photo), 404);

        return response()->file(Storage::disk('local')->path($employee->photo), ['Cache-Control' => 'private, max-age=86400']);
    }

    public function cardPhoto(IdCard $card)
    {
        abort_unless($card->photo && Storage::disk('local')->exists($card->photo), 404);

        return response()->file(Storage::disk('local')->path($card->photo), ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Public, signed: what the QR on a card opens. */
    public function verifyCard(IdCard $card)
    {
        return response()->view('attendance.idcards.verify', [
            'card' => $card,
            'photo' => $this->hr->fileDataUrl($card->photo),
            'settings' => $this->hr->settings(),
        ])->header('X-Robots-Tag', 'noindex');
    }

    /** Older employee QR links: show the employee's latest card. */
    public function verify(Employee $employee)
    {
        $card = IdCard::where('employee_id', $employee->id)->latest('id')->first()
            ?? new IdCard(['name' => $employee->name, 'designation' => $employee->designation, 'department' => $employee->department,
                'id_no' => $employee->empId(), 'joining_date' => $employee->joining_date, 'issue_date' => today(), 'employee_id' => $employee->id]);

        return response()->view('attendance.idcards.verify', [
            'card' => $card,
            'photo' => $this->hr->fileDataUrl($card->photo ?? $employee->photo),
            'settings' => $this->hr->settings(),
        ])->header('X-Robots-Tag', 'noindex');
    }

    /** @return array{card: array, photo: ?string} */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'id_no' => 'nullable|string|max:30',
            'blood_group' => ['nullable', Rule::in(Employee::BLOOD_GROUPS)],
            'phone' => 'nullable|string|max:50',
            'joining_date' => 'nullable|date',
            'issue_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:issue_date',
            'theme' => ['nullable', Rule::in(array_keys(HrDocs::THEMES))],
            'photo' => 'nullable|string|max:12000000',
        ], ['expiry_date.after' => 'The expiry date has to be after the issue date.']);

        $photo = filled($data['photo'] ?? null) && str_starts_with($data['photo'], 'data:') ? $data['photo'] : null;
        unset($data['photo']);

        return ['card' => $data, 'photo' => $photo];
    }

    /** A new photo if one was chosen, else a copy of the employee's. */
    protected function photoFor(IdCard $card, ?string $photo): ?string
    {
        if ($photo) {
            return $this->hr->savePhoto($photo);
        }

        return $card->employee_id ? $this->hr->copyPhoto(Employee::find($card->employee_id)?->photo) : null;
    }

    /** "Also keep these details on the employee's record." */
    protected function toEmployee(IdCard $card, Request $request): void
    {
        $employee = $card->employee;
        if (! $employee || ! $request->boolean('save_to_employee')) {
            return;
        }

        $employee->update(array_filter([
            'designation' => $card->designation,
            'department' => $card->department,
            'blood_group' => $card->blood_group,
            'phone' => $card->phone,
            'joining_date' => $card->joining_date,
            'emp_code' => $card->id_no && ! Employee::where('emp_code', $card->id_no)->whereKeyNot($employee->id)->exists() ? $card->id_no : null,
        ], fn ($v) => $v !== null && $v !== ''));

        if ($card->photo && $request->filled('photo')) {
            $old = $employee->photo;
            $employee->forceFill(['photo' => $this->hr->copyPhoto($card->photo)])->save();
            if ($old && ! IdCard::where('photo', $old)->exists()) {
                Storage::disk('local')->delete($old);
            }
        }
    }
}
