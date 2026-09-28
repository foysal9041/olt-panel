<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class BandwidthType extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
    ];

    public function customerRates()
    {
        return $this->hasMany(CustomerBandwidthRate::class);
    }
}
