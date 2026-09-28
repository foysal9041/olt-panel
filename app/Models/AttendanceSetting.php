<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $fillable = [
        'weekly_off_day',
        'office_start_time',
        'late_grace_minutes',
    ];

    public static function current(): self
    {
        // Explicit defaults here (not just the migration's column defaults)
        // so the in-memory instance is populated immediately on first use —
        // Eloquent doesn't reload DB-applied defaults after an insert.
        return static::query()->firstOrCreate([], [
            'weekly_off_day' => 'Friday',
            'office_start_time' => '09:00:00',
            'late_grace_minutes' => 15,
        ]);
    }
}
