<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/** An office ID card given to someone (an employee or anyone else). */
class IdCard extends Model
{
    use LogsActivity;

    protected $fillable = [
        'card_no', 'employee_id', 'name', 'designation', 'department', 'id_no', 'blood_group', 'phone',
        'joining_date', 'issue_date', 'expiry_date', 'photo', 'theme', 'revoked_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'revoked_at' => 'datetime',
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

    /** valid · expired · revoked · left (the employee no longer works here) */
    public function status(): string
    {
        return match (true) {
            $this->revoked_at !== null => 'revoked',
            $this->employee && $this->employee->hasLeft() => 'left',
            $this->expiry_date && $this->expiry_date->lt(today()) => 'expired',
            default => 'valid',
        };
    }

    public function isValid(): bool
    {
        return $this->status() === 'valid';
    }

    /** Public link printed as the card's QR. Signed without the host. */
    public function verifyUrl(): string
    {
        return rtrim(config('app.url'), '/') . URL::signedRoute('card.verify', ['card' => $this->id], absolute: false);
    }

    /** SNDC-IDC-0007 — the next card number. */
    public static function nextNumber(): string
    {
        $last = static::where('card_no', 'like', 'SNDC-IDC-%')->pluck('card_no')->map(fn ($n) => (int) substr($n, 9))->max() ?? 0;

        return 'SNDC-IDC-' . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    protected function activityLogTitle(): string
    {
        return $this->card_no . ' ' . $this->name;
    }
}
