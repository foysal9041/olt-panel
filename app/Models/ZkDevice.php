<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ZkDevice extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'zone',
        'serial_number',
        'ip_address',
        'last_seen_at',
        'clock_offset_minutes',
        'pending_command',
        'pending_command_id',
        'command_issued_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'command_issued_at' => 'datetime',
        ];
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at
            && $this->last_seen_at->gt(now()->subMinutes(10));
    }

    public function hasPendingCommand(): bool
    {
        return !empty($this->pending_command);
    }

    /**
     * Some devices have no timezone setting and drift by a fixed amount
     * whenever they sync from the network (e.g. a hardcoded factory GMT
     * offset). clock_offset_minutes corrects for that: it's how many
     * minutes AHEAD of true time the device reports (positive), or behind
     * (negative) — subtracted from whatever time the device reports to
     * recover the true time.
     */
    public function correctTime(\Illuminate\Support\Carbon $deviceReportedTime): \Illuminate\Support\Carbon
    {
        if ($this->clock_offset_minutes === 0) {
            return $deviceReportedTime;
        }

        return $deviceReportedTime->copy()->subMinutes($this->clock_offset_minutes);
    }
}
