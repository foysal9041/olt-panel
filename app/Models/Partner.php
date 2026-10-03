<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * A shareholder and/or commission holder, with an account of their own:
 * what each finalized month credited them (profit share, commission) and
 * what was paid out to them. Balance = still to be paid.
 */
class Partner extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'phone', 'share', 'commission_percent', 'is_active', 'sort', 'notes'];

    protected $casts = [
        'share' => 'string',
        'commission_percent' => 'string',
        'is_active' => 'boolean',
    ];

    public function entries()
    {
        return $this->hasMany(PartnerEntry::class)->orderBy('entry_date')->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    public function isShareholder(): bool
    {
        return (float) $this->share > 0;
    }

    public function isCommissionHolder(): bool
    {
        return (float) $this->commission_percent > 0;
    }

    public function roleLabel(): string
    {
        return implode(' · ', array_filter([
            $this->isShareholder() ? rtrim(rtrim($this->share, '0'), '.') . ' shares' : null,
            $this->isCommissionHolder() ? rtrim(rtrim($this->commission_percent, '0'), '.') . '% commission' : null,
        ])) ?: 'No share or commission';
    }

    protected function activityLogLabel(): string
    {
        return 'Partner';
    }

    protected function activityLogTitle(): string
    {
        return $this->name;
    }
}
