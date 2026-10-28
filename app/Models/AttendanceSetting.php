<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AttendanceSetting extends Model
{
    use LogsActivity;

    protected $fillable = [
        'weekly_off_day',
        'punch_from',
        'punch_until',
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

    /** Office time is set: punches outside it don't count. */
    public function hasPunchWindow(): bool
    {
        return (bool) ($this->punch_from && $this->punch_until);
    }

    /** Office time ends after midnight (e.g. 8:00 AM – 2:00 AM). */
    public function punchWindowWraps(): bool
    {
        return $this->hasPunchWindow() && $this->untilTime() <= $this->fromTime();
    }

    /**
     * The attendance day a punch counts for: its own date, the day before
     * for an after-midnight punch when office time runs past midnight,
     * or null when it's outside office time.
     */
    public function workDay(Carbon $punch): ?string
    {
        if (! $this->hasPunchWindow()) {
            return $punch->toDateString();
        }
        $time = $punch->format('H:i:s');

        if (! $this->punchWindowWraps()) {
            return $time >= $this->fromTime() && $time <= $this->untilTime() ? $punch->toDateString() : null;
        }
        if ($time >= $this->fromTime()) {
            return $punch->toDateString();
        }

        return $time <= $this->untilTime() ? $punch->copy()->subDay()->toDateString() : null;
    }

    /**
     * When the attendance days $from … $to start and end: office time
     * (into the next morning when it wraps), else the whole calendar days.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function workDayRange(Carbon $from, ?Carbon $to = null): array
    {
        $to ??= $from;
        if (! $this->hasPunchWindow()) {
            return [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        }
        $end = $to->copy()->setTimeFromTimeString($this->untilTime());

        return [$from->copy()->setTimeFromTimeString($this->fromTime()), $this->punchWindowWraps() ? $end->addDay() : $end];
    }

    /** "08:00 AM – 11:59 PM", or null for the whole day. */
    public function punchWindowLabel(): ?string
    {
        return $this->hasPunchWindow()
            ? Carbon::parse($this->punch_from)->format('h:i A') . ' – ' . Carbon::parse($this->punch_until)->format('h:i A')
            : null;
    }

    protected function fromTime(): string
    {
        return substr($this->punch_from, 0, 5) . ':00';
    }

    /** The end minute counts in full: until 23:00 takes a punch at 23:00:40. */
    protected function untilTime(): string
    {
        return substr($this->punch_until, 0, 5) . ':59';
    }

    protected function activityLogLabel(): string
    {
        return 'Attendance Settings';
    }

    protected function activityLogTitle(): string
    {
        return 'office hours & rules';
    }
}
