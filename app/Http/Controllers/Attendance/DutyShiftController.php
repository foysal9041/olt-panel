<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\DutyShift;
use Illuminate\Http\Request;

class DutyShiftController extends Controller
{
    public function index()
    {
        $shifts = DutyShift::withCount('employees')->orderBy('start_time')->get();

        return view('attendance.shifts.index', compact('shifts'));
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('attendance.shifts.create');
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $this->validated($request);

        DutyShift::create($validated);

        return redirect()
            ->route('attendance.shifts.index')
            ->with('success', 'Duty Shift Created Successfully');
    }

    public function edit(DutyShift $shift)
    {
        $this->authorizeAdmin();

        return view('attendance.shifts.edit', compact('shift'));
    }

    public function update(Request $request, DutyShift $shift)
    {
        $this->authorizeAdmin();

        $shift->update($this->validated($request));

        return redirect()
            ->route('attendance.shifts.index')
            ->with('success', 'Duty Shift Updated Successfully');
    }

    public function destroy(DutyShift $shift)
    {
        $this->authorizeAdmin();

        if ($shift->is_default) {
            return back()->with('error', 'The default shift cannot be deleted.');
        }

        if ($shift->employees()->exists()) {
            return back()->with('error', 'This shift is assigned to employees and cannot be deleted.');
        }

        $shift->delete();

        return redirect()
            ->route('attendance.shifts.index')
            ->with('success', 'Duty Shift Deleted Successfully');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'late_grace_minutes' => 'required|integer|min:0|max:120',
            'is_default' => 'nullable|boolean',
        ]);

        $validated['is_default'] = $request->boolean('is_default');

        if ($validated['is_default']) {
            DutyShift::where('is_default', true)->update(['is_default' => false]);
        }

        return $validated;
    }

    private function authorizeAdmin(): void
    {
        if (strtolower(auth()->user()->role) !== 'admin') {
            abort(403);
        }
    }
}
