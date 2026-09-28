<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\ZkDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Receives pushes from ZKTeco devices (e.g. the F18) configured in
 * "Cloud Server / ADMS" mode. Paths below are fixed by the device firmware
 * and cannot be changed on our side.
 */
class ZkPushController extends Controller
{
    public function cdata(Request $request): Response
    {
        $serial = $request->query('SN', $request->query('sn'));

        if (!$serial) {
            return $this->plain('OK');
        }

        $device = $this->touchDevice($serial, $request);

        if ($request->isMethod('post')) {
            return $this->storeAttendanceLogs($device, $request);
        }

        // Note: deliberately no "TransTimes=" line here — some ZKTeco
        // firmware has been observed misreading a scheduled-transfer-time
        // hint in that field as a clock-sync instruction, shifting the
        // device's own clock to match. Realtime=1 already covers pushing
        // data immediately, so a transfer schedule isn't needed anyway.
        return $this->plain(
            "GET OPTION FROM: {$serial}\r\n" .
            "Stamp=9999\r\n" .
            "OpStamp=9999\r\n" .
            "ErrorDelay=30\r\n" .
            "Delay=10\r\n" .
            "TransInterval=1\r\n" .
            "TransFlag=1111000000\r\n" .
            "Realtime=1\r\n" .
            "Encrypt=0\r\n"
        );
    }

    public function getrequest(Request $request): Response
    {
        $serial = $request->query('SN', $request->query('sn'));

        if (!$serial) {
            return $this->plain('OK');
        }

        $device = $this->touchDevice($serial, $request);

        if ($device->hasPendingCommand()) {
            return $this->plain("C:{$device->pending_command_id}:{$device->pending_command}");
        }

        return $this->plain('OK');
    }

    public function devicecmd(Request $request): Response
    {
        $serial = $request->query('SN', $request->query('sn'));

        if (!$serial) {
            return $this->plain('OK');
        }

        $device = $this->touchDevice($serial, $request);

        // The device reports command results as "ID=<id>&Return=<code>&CMD=..."
        // in the body (or query string, depending on firmware) — look for the
        // pending command's ID wherever it shows up and clear the queue once
        // the device confirms it picked the command up.
        $report = $request->getContent() . ' ' . $request->getQueryString();

        if ($device->pending_command_id && str_contains($report, "ID={$device->pending_command_id}")) {
            $device->update([
                'pending_command' => null,
                'pending_command_id' => null,
            ]);
        }

        return $this->plain('OK');
    }

    public function fdata(Request $request): Response
    {
        $this->touchDeviceIfPresent($request);

        return $this->plain('OK');
    }

    private function touchDeviceIfPresent(Request $request): void
    {
        $serial = $request->query('SN', $request->query('sn'));

        if ($serial) {
            $this->touchDevice($serial, $request);
        }
    }

    private function touchDevice(string $serial, Request $request): ZkDevice
    {
        return ZkDevice::updateOrCreate(
            ['serial_number' => $serial],
            [
                'ip_address' => $request->ip(),
                'last_seen_at' => now(),
            ]
        );
    }

    private function storeAttendanceLogs(ZkDevice $device, Request $request): Response
    {
        if ($request->query('table') !== 'ATTLOG') {
            return $this->plain('OK');
        }

        $lines = preg_split('/\r\n|\r|\n/', trim((string) $request->getContent()));

        $employeeIdsByPin = Employee::whereNotNull('device_user_id')
            ->pluck('id', 'device_user_id');

        foreach ($lines as $line) {
            $this->storeAttendanceLine($device, $line, $employeeIdsByPin);
        }

        return $this->plain('OK');
    }

    private function storeAttendanceLine(ZkDevice $device, string $line, $employeeIdsByPin): void
    {
        $line = trim($line);

        if ($line === '') {
            return;
        }

        // Standard ADMS ATTLOG line: PIN\tTime\tStatus\tVerify\tWorkcode\t...
        $fields = preg_split('/\t+/', $line);
        $pin = $fields[0] ?? null;
        $time = $fields[1] ?? null;

        if (!$pin || !$time) {
            return;
        }

        try {
            $punchedAt = $device->correctTime(Carbon::parse($time));
        } catch (\Throwable $e) {
            return;
        }

        AttendanceLog::firstOrCreate(
            [
                'zk_device_id' => $device->id,
                'device_user_id' => $pin,
                'punched_at' => $punchedAt,
            ],
            [
                'employee_id' => $employeeIdsByPin[$pin] ?? null,
                'status_code' => isset($fields[2]) ? (int) $fields[2] : null,
                'verify_mode' => isset($fields[3]) ? (int) $fields[3] : null,
                'created_at' => now(),
            ]
        );
    }

    /**
     * Reported by two different physical devices now: the moment a ZKTeco
     * device connects to our ADMS server, its own clock jumps 2 hours ahead
     * of true Dhaka time — and it has no timezone setting to fix on its
     * side. That points at these devices syncing their clock from our
     * server's HTTP response (the only "network time" we hand them) and
     * then applying a hardcoded, non-configurable GMT+8 offset on top,
     * landing 2 hours past correct GMT+6.
     *
     * Experimental root-cause fix: reply with a Date header shifted 2 hours
     * behind true UTC on every iclock/* response, so device_time =
     * (true_UTC - 2h) + 8h = true_UTC + 6h = correct Dhaka time. This needs
     * confirming on the physical devices' own displayed clock — if it
     * doesn't help, set this back to 0 and rely on each device's
     * clock_offset_minutes (Attendance > Devices > Edit) instead. Don't run
     * both corrections at once on the same device — that double-corrects.
     */
    private const DATE_HEADER_OFFSET_MINUTES = -120;

    private function plain(string $body): Response
    {
        $response = response($body, 200)->header('Content-Type', 'text/plain');

        if (self::DATE_HEADER_OFFSET_MINUTES !== 0) {
            $response->header(
                'Date',
                now('UTC')->addMinutes(self::DATE_HEADER_OFFSET_MINUTES)->format('D, d M Y H:i:s \G\M\T')
            );
        }

        return $response;
    }
}
