<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use App\Models\ZkDevice;
use Illuminate\Http\Request;

class ZkDeviceController extends Controller
{
    public function index()
    {
        $devices = ZkDevice::orderByDesc('last_seen_at')->get();
        $settings = AttendanceSetting::current();

        return view('attendance.devices.index', compact('devices', 'settings'));
    }

    public function create()
    {

        return view('attendance.devices.create', ['zones' => \App\Models\Zone::names()]);
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'serial_number' => 'required|string|max:255|unique:zk_devices,serial_number',
            'zone' => 'nullable|exists:zones,name',
        ]);

        ZkDevice::create($validated);

        return redirect()
            ->route('attendance.devices.index')
            ->with('success', 'Device Added Successfully. It will come online as soon as it connects.');
    }

    public function edit(ZkDevice $device)
    {

        return view('attendance.devices.edit', compact('device') + ['zones' => \App\Models\Zone::names()]);
    }

    public function update(Request $request, ZkDevice $device)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'zone' => 'nullable|exists:zones,name',
            'clock_offset_minutes' => 'nullable|integer|min:-720|max:720',
        ]);

        $validated['clock_offset_minutes'] = $validated['clock_offset_minutes'] ?? 0;

        $device->update($validated);

        return redirect()
            ->route('attendance.devices.index')
            ->with('success', 'Device Updated Successfully');
    }

    public function destroy(ZkDevice $device)
    {
        $this->authorizeAdmin();

        $device->delete();

        return redirect()
            ->route('attendance.devices.index')
            ->with('success', 'Device Removed Successfully');
    }

    /**
     * Ask the device to re-send attendance logs it already has stored on it
     * (e.g. punches recorded before ADMS was configured, or during downtime).
     * The command is delivered the next time the device polls /iclock/getrequest
     * and cleared once it reports back via /iclock/devicecmd.
     */
    public function fetchData(ZkDevice $device)
    {

        $commandId = (string) now()->timestamp;

        $device->update([
            'pending_command' => sprintf(
                "DATA QUERY ATTLOG StartTime=%s\tEndTime=%s",
                now()->subYears(5)->format('Y-m-d 00:00:00'),
                now()->format('Y-m-d H:i:s')
            ),
            'pending_command_id' => $commandId,
            'command_issued_at' => now(),
        ]);

        return back()->with('success', 'Fetch requested — the device will upload its stored logs the next time it checks in.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only an admin can delete a device.');
    }
}
