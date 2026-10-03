<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** An appointment or termination letter, kept so it can be printed again. */
class HrLetter extends Model
{
    use LogsActivity;

    /** type => [label, ref code, icon, colour] */
    public const TYPES = [
        'appointment' => ['Appointment Letter', 'APT', 'fas fa-file-signature', '#16a34a'],
        'termination' => ['Termination Letter', 'TRM', 'fas fa-file-excel', '#dc2626'],
    ];

    /** Why the job ends — sets the letter's opening paragraph. */
    public const REASONS = [
        'resignation' => 'Resignation accepted',
        'contract_end' => 'End of contract',
        'redundancy' => 'Redundancy / restructuring',
        'performance' => 'Unsatisfactory performance',
        'absence' => 'Unauthorised absence',
        'misconduct' => 'Misconduct',
        'probation' => 'Probation not confirmed',
        'other' => 'Other',
    ];

    protected $fillable = ['employee_id', 'type', 'ref_no', 'letter_date', 'data', 'created_by'];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'data' => 'array',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function name(): string
    {
        return $this->data['name'] ?? $this->employee?->name ?? '—';
    }

    /** "SNDC/HR/APT/2026/003" — the next number for this type and year. */
    public static function nextRef(string $type, \DateTimeInterface $date): string
    {
        $prefix = 'SNDC/HR/' . (self::TYPES[$type][1] ?? 'LTR') . '/' . $date->format('Y') . '/';
        $last = static::where('ref_no', 'like', $prefix . '%')->pluck('ref_no')
            ->map(fn ($r) => (int) substr($r, strlen($prefix)))->max() ?? 0;

        return $prefix . str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }

    protected function activityLogTitle(): string
    {
        return $this->typeLabel() . ' ' . $this->ref_no . ' — ' . $this->name();
    }
}
