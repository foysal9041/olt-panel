<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class BandwidthType extends Model
{
    use LogsActivity;

    /** flat = a fixed monthly charge (VAS, Billing), not billed by days. */
    protected $fillable = [
        'name',
        'flat',
        'sort',
    ];

    protected $casts = [
        'flat' => 'boolean',
    ];

    public function customerRates()
    {
        return $this->hasMany(CustomerBandwidthRate::class);
    }

    public function serviceChanges()
    {
        return $this->hasMany(BandwidthServiceChange::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort')->orderBy('name');
    }
}
