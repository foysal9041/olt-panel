<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class DutyShift extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'late_grace_minutes',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** "09:00 AM – 06:00 PM", or just the start when no end is set. */
    public function hoursLabel(): string
    {
        $start = \Illuminate\Support\Carbon::parse($this->start_time)->format('h:i A');

        return $this->end_time ? $start . ' – ' . \Illuminate\Support\Carbon::parse($this->end_time)->format('h:i A') : $start;
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * The shift used for employees who haven't been assigned one.
     * Created on first use, seeded from the legacy global AttendanceSetting
     * so behaviour doesn't change for anyone until shifts are set up.
     */
    public static function default(): self
    {
        $default = static::where('is_default', true)->first();

        if ($default) {
            return $default;
        }

        $settings = AttendanceSetting::current();

        return static::create([
            'name' => 'Default Shift',
            'start_time' => $settings->office_start_time,
            'late_grace_minutes' => $settings->late_grace_minutes,
            'is_default' => true,
        ]);
    }
}
