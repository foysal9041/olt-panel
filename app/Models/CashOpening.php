<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashOpening extends Model
{
    protected $fillable = ['date', 'amount', 'note', 'recorded_by'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'float',
    ];

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
