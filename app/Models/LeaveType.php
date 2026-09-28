<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'default_days_per_year',
    ];

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }
}
