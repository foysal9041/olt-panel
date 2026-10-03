<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * One line in a partner's account. Credits come from a finalized Net
 * Profit sheet (share, commission); payments are money handed over
 * (negative); adjustments correct either way.
 */
class PartnerEntry extends Model
{
    use LogsActivity;

    public const TYPES = [
        'share' => 'Profit share',
        'commission' => 'Commission',
        'payment' => 'Payment',
        'adjustment' => 'Adjustment',
    ];

    public const METHODS = ['Bank', 'bKash', 'Nagad', 'Cash', 'Other'];

    protected $fillable = ['partner_id', 'profit_sheet_id', 'entry_date', 'type', 'amount', 'method', 'note', 'transaction_id', 'recorded_by'];

    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'string',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function sheet()
    {
        return $this->belongsTo(ProfitSheet::class, 'profit_sheet_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    /** Entries made by finalizing a month; they go away only by reopening it. */
    public function isFromSheet(): bool
    {
        return in_array($this->type, ['share', 'commission'], true) && $this->profit_sheet_id;
    }

    protected function activityLogLabel(): string
    {
        return 'Partner Entry';
    }

    protected function activityLogTitle(): string
    {
        return ($this->partner?->name ?? '#' . $this->partner_id) . ' · ' . $this->typeLabel() . ' ' . $this->amount;
    }
}
